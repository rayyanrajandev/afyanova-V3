<?php

namespace App\Domains\Clinical\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\ClinicalReferral;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Patient\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateReferralAction
{
    public function execute(Patient $patient, array $data, ?Encounter $encounter = null): ClinicalReferral
    {
        if ($patient->isDeceased()) {
            throw new InvalidArgumentException('Cannot create referral for a deceased patient.');
        }
        if ($patient->isMerged()) {
            throw new InvalidArgumentException('Cannot create referral on a merged patient record.');
        }

        return DB::transaction(function () use ($patient, $data, $encounter) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $patient->tenant_id;
            $fromFacilityId = $encounter?->facility_id ?? app(FacilityContext::class)->getFacilityId();

            if (! $fromFacilityId) {
                throw new InvalidArgumentException('Unable to determine the referring facility — no encounter and no facility context.');
            }

            $referralNumber = 'REF-'.date('Y').'-'.strtoupper(Str::random(6));

            return ClinicalReferral::create([
                'tenant_id' => $tenantId,
                'from_facility_id' => $fromFacilityId,
                'to_facility_id' => $data['to_facility_id'] ?? null,
                'external_facility_name' => $data['external_facility_name'] ?? null,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter?->id,
                'referring_doctor_id' => auth()->id() ?? $data['referring_doctor_id'],
                'referral_number' => $referralNumber,
                'urgency' => $data['urgency'] ?? 'Routine',
                'specialty_required' => $data['specialty_required'],
                'clinical_summary' => $data['clinical_summary'],
                'investigations_performed' => $data['investigations_performed'] ?? null,
                'treatments_given' => $data['treatments_given'] ?? null,
                'reason_for_referral' => $data['reason_for_referral'],
                'transport_mode' => $data['transport_mode'] ?? 'Ambulance',
                'status' => 'Dispatched',
                'dispatched_at' => now(),
            ]);
        });
    }
}
