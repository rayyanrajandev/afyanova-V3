<?php

namespace App\Domains\Inpatient\Actions;

use App\Domains\Clinical\Models\Encounter;
use App\Domains\Inpatient\Exceptions\InpatientException;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdmitPatientAction
{
    public function execute(array $data): Admission
    {
        $patientId = $data['patient_id'] ?? null;
        $bedId = $data['bed_id'] ?? null;
        $wardId = $data['ward_id'] ?? null;
        $doctorId = $data['admitting_doctor_id'] ?? auth()->id();
        $reason = $data['admission_reason'] ?? 'Inpatient medical management and monitoring';
        $diagnosis = $data['provisional_diagnosis'] ?? null;

        if (! $patientId) {
            throw new InvalidArgumentException('Patient ID is required for admission.');
        }
        if (! $bedId) {
            throw new InvalidArgumentException('Bed ID is required for admission.');
        }

        $patient = Patient::findOrFail($patientId);

        // Invariant: Cannot admit deceased or merged patient
        if ($patient->isDeceased()) {
            throw InpatientException::patientDeceased("{$patient->first_name} {$patient->last_name}");
        }

        if ($patient->isMerged()) {
            throw new InvalidArgumentException("Cannot admit patient {$patient->first_name} {$patient->last_name}. Patient record has been merged into {$patient->merged_into_patient_id}.");
        }

        return DB::transaction(function () use ($data, $patient, $patientId, $bedId, $wardId, $doctorId, $reason, $diagnosis) {
            // 1. Lock bed row for update to prevent concurrent double-booking
            $bed = Bed::where('id', $bedId)->lockForUpdate()->firstOrFail();
            $ward = $wardId ? Ward::findOrFail($wardId) : $bed->ward;

            // 2. Check bed availability under lock
            if ($bed->status !== 'Available') {
                throw InpatientException::bedNotAvailable($bed->bed_number, $bed->status);
            }

            // 3. Check if patient is already admitted under lock
            $existingAdmission = Admission::where('patient_id', $patientId)
                ->where('status', 'Admitted')
                ->lockForUpdate()
                ->first();

            if ($existingAdmission) {
                throw InpatientException::patientAlreadyAdmitted("{$patient->first_name} {$patient->last_name}");
            }

            $tenantId = $bed->tenant_id ?? auth()->user()?->tenant_id ?? Tenant::first()?->id;
            $facilityId = $bed->facility_id ?? auth()->user()?->facility_id ?? Facility::where('tenant_id', $tenantId)->first()?->id ?? Facility::first()?->id;

            // 4. Mark Bed as Occupied
            $bed->update(['status' => 'Occupied']);

            // 5. Find or create active Encounter of type 'IPD'
            $encounter = null;
            if (! empty($data['encounter_id'])) {
                $encounter = Encounter::find((string) $data['encounter_id']);
                if ($encounter) {
                    $encounter->update([
                        'encounter_type' => 'IPD',
                        'status' => 'In Progress',
                    ]);
                }
            }

            if (! $encounter) {
                $encounter = Encounter::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'patient_id' => $patient->id,
                    'provider_id' => $doctorId,
                    'encounter_type' => 'IPD',
                    'status' => 'In Progress',
                    'start_time' => now(),
                ]);
            }

            // 6. Generate Admission Number
            $todayYear = date('Y');
            $count = Admission::whereYear('created_at', $todayYear)->count() + 1;
            $admissionNumber = sprintf('ADM-%s-%04d', $todayYear, $count);

            // 7. Create Admission Record
            $admission = Admission::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'encounter_id' => $encounter->id,
                'patient_id' => $patient->id,
                'admitting_doctor_id' => $doctorId,
                'ward_id' => $ward->id,
                'bed_id' => $bed->id,
                'admission_number' => $admissionNumber,
                'admission_reason' => $reason,
                'provisional_diagnosis' => $diagnosis,
                'admitted_at' => now(),
                'status' => 'Admitted',
            ]);

            return $admission;
        });
    }
}
