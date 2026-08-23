<?php

namespace App\Domains\Clinical\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\AncEncounter;
use App\Domains\Clinical\Models\Encounter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordAncVisitAction
{
    public function execute(Encounter $encounter, array $data): AncEncounter
    {
        $patient = $encounter->patient;
        if ($patient?->isDeceased()) {
            throw new InvalidArgumentException('Cannot record ANC visit for a deceased patient.');
        }
        if ($patient?->isMerged()) {
            throw new InvalidArgumentException('Cannot record ANC visit on a merged patient record.');
        }

        return DB::transaction(function () use ($encounter, $data) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $encounter->tenant_id;

            return AncEncounter::create([
                'tenant_id' => $tenantId,
                'facility_id' => $encounter->facility_id,
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'midwife_id' => auth()->id() ?? $data['midwife_id'] ?? $encounter->provider_id,
                'gravida' => $data['gravida'] ?? 1,
                'para' => $data['para'] ?? 0,
                'last_menstrual_period' => $data['last_menstrual_period'] ?? null,
                'estimated_date_of_delivery' => $data['estimated_date_of_delivery'] ?? null,
                'gestational_age_weeks' => $data['gestational_age_weeks'] ?? null,
                'fundal_height_cm' => $data['fundal_height_cm'] ?? null,
                'fetal_presentation' => $data['fetal_presentation'] ?? 'Cephalic',
                'fetal_heart_rate_bpm' => $data['fetal_heart_rate_bpm'] ?? null,
                'fetal_movement' => $data['fetal_movement'] ?? 'Normal',
                'urinary_protein' => $data['urinary_protein'] ?? 0.0,
                'iptp_malaria_dose' => $data['iptp_malaria_dose'] ?? null,
                'iron_folate_given' => $data['iron_folate_given'] ?? true,
                'high_risk_flag' => $data['high_risk_flag'] ?? false,
                'high_risk_reason' => $data['high_risk_reason'] ?? null,
            ]);
        });
    }
}
