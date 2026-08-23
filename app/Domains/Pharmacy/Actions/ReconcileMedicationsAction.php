<?php

namespace App\Domains\Pharmacy\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\MedicationReconciliation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReconcileMedicationsAction
{
    /**
     * Reconcile a batch of medications during patient admission, transfer, or discharge.
     *
     * @param  string  $stage  'Admission', 'Transfer', or 'Discharge'
     * @param  array  $medications  Array of reconciled drug items
     * @return Collection<int, MedicationReconciliation>
     */
    public function execute(
        Patient $patient,
        string $stage,
        array $medications,
        ?string $facilityId = null,
        ?string $encounterId = null,
        ?string $admissionId = null
    ): Collection {
        if ($patient->isDeceased()) {
            throw new InvalidArgumentException('Cannot perform medication reconciliation for a deceased patient.');
        }

        if ($patient->isMerged()) {
            throw new InvalidArgumentException('Cannot perform medication reconciliation on a merged patient record.');
        }

        return DB::transaction(function () use ($patient, $stage, $medications, $facilityId, $encounterId, $admissionId) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $patient->tenant_id;
            $facilityId ??= app(FacilityContext::class)->getFacilityId();
            $userId = auth()->id();

            if (! $facilityId) {
                throw new InvalidArgumentException('Unable to determine the facility for this medication reconciliation — pass one explicitly or set a facility context.');
            }

            $records = collect();

            foreach ($medications as $item) {
                $record = MedicationReconciliation::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'patient_id' => $patient->id,
                    'encounter_id' => $encounterId,
                    'admission_id' => $admissionId,
                    'reconciled_by' => $userId,
                    'stage' => $stage,
                    'medication_name' => $item['medication_name'],
                    'dosage' => $item['dosage'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'route' => $item['route'] ?? null,
                    'action_taken' => $item['action_taken'], // Continue, Discontinue, Substitute, ModifyDose, Hold
                    'clinical_rationale' => $item['clinical_rationale'] ?? null,
                    'substitute_medication_name' => $item['substitute_medication_name'] ?? null,
                    'new_dosage_instructions' => $item['new_dosage_instructions'] ?? null,
                    'reconciled_at' => now(),
                ]);

                $records->push($record);
            }

            return $records;
        });
    }
}
