<?php

namespace App\Domains\Clinical\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\PatientImmunization;
use App\Domains\Patient\Models\Patient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdministerImmunizationAction
{
    public function execute(Patient $patient, array $data, ?Encounter $encounter = null): PatientImmunization
    {
        if ($patient->isDeceased()) {
            throw new InvalidArgumentException('Cannot administer immunization to a deceased patient.');
        }
        if ($patient->isMerged()) {
            throw new InvalidArgumentException('Cannot administer immunization on a merged patient record.');
        }

        return DB::transaction(function () use ($patient, $data, $encounter) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $patient->tenant_id;
            $facilityId = $encounter?->facility_id ?? app(FacilityContext::class)->getFacilityId();

            if (! $facilityId) {
                throw new InvalidArgumentException('Unable to determine the facility for this immunization — no encounter and no facility context.');
            }

            return PatientImmunization::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter?->id,
                'administered_by' => auth()->id() ?? $data['administered_by'],
                'vaccine_code' => $data['vaccine_code'],
                'vaccine_name' => $data['vaccine_name'],
                'dose_number' => $data['dose_number'] ?? 1,
                'batch_number' => $data['batch_number'] ?? null,
                'expiration_date' => $data['expiration_date'] ?? null,
                'administration_site' => $data['administration_site'] ?? 'Left Deltoid',
                'route' => $data['route'] ?? 'Intramuscular',
                'adverse_reaction_notes' => $data['adverse_reaction_notes'] ?? null,
                'administered_at' => now(),
                'next_due_date' => $data['next_due_date'] ?? null,
            ]);
        });
    }
}
