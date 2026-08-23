<?php

namespace App\Domains\Clinical\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\ClinicalConsent;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Patient\Models\Patient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordConsentAction
{
    public function execute(Patient $patient, array $data, ?Encounter $encounter = null): ClinicalConsent
    {
        if ($patient->isDeceased()) {
            throw new InvalidArgumentException('Cannot record consent for a deceased patient.');
        }
        if ($patient->isMerged()) {
            throw new InvalidArgumentException('Cannot record consent on a merged patient record.');
        }

        return DB::transaction(function () use ($patient, $data, $encounter) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $patient->tenant_id;
            $facilityId = $encounter?->facility_id ?? app(FacilityContext::class)->getFacilityId();

            if (! $facilityId) {
                throw new InvalidArgumentException('Unable to determine the facility for this consent — no encounter and no facility context.');
            }

            return ClinicalConsent::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter?->id,
                'procedure_order_id' => $data['procedure_order_id'] ?? null,
                'clinician_id' => auth()->id() ?? $data['clinician_id'],
                'consent_type' => $data['consent_type'],
                'procedure_title' => $data['procedure_title'],
                'explanation_of_risks' => $data['explanation_of_risks'],
                'alternative_treatments' => $data['alternative_treatments'] ?? null,
                'signatory_type' => $data['signatory_type'] ?? 'Patient',
                'signatory_name' => $data['signatory_name'],
                'signature_fingerprint_token' => $data['signature_fingerprint_token'] ?? null,
                'witness_name' => $data['witness_name'] ?? null,
                'interpreter_used' => $data['interpreter_used'] ?? false,
                'language_used' => $data['language_used'] ?? 'Swahili',
                'signed_at' => now(),
            ]);
        });
    }
}
