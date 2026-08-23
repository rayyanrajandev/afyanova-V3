<?php

namespace App\Domains\Clinical\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\PartographEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordPartographAction
{
    public function execute(Encounter $encounter, array $data): PartographEntry
    {
        $patient = $encounter->patient;
        if ($patient?->isDeceased()) {
            throw new InvalidArgumentException('Cannot record labor telemetry for a deceased patient.');
        }
        if ($patient?->isMerged()) {
            throw new InvalidArgumentException('Cannot record labor telemetry on a merged patient record.');
        }

        return DB::transaction(function () use ($encounter, $data) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $encounter->tenant_id;

            $dilation = floatval($data['cervical_dilation_cm']);
            $fhr = intval($data['fetal_heart_rate_bpm']);

            // Alert line: cervical dilation rate < 1cm/hr during active phase (>= 4cm)
            // Action line: 4 hours to the right of the alert line
            $alertLineCrossed = $data['alert_line_crossed'] ?? ($dilation >= 4.0 && ($data['slow_progress'] ?? false));
            $actionLineCrossed = $data['action_line_crossed'] ?? false;

            return PartographEntry::create([
                'tenant_id' => $tenantId,
                'facility_id' => $encounter->facility_id,
                'anc_encounter_id' => $data['anc_encounter_id'] ?? null,
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'recorded_by' => auth()->id() ?? $data['recorded_by'] ?? $encounter->provider_id,
                'cervical_dilation_cm' => $dilation,
                'fetal_heart_rate_bpm' => $fhr,
                'liquor_status' => $data['liquor_status'] ?? 'Clear',
                'fetal_head_descent' => $data['fetal_head_descent'] ?? '3/5',
                'uterine_contractions_per_10min' => intval($data['uterine_contractions_per_10min'] ?? 3),
                'contraction_duration_seconds' => intval($data['contraction_duration_seconds'] ?? 40),
                'maternal_systolic_bp' => $data['maternal_systolic_bp'] ?? null,
                'maternal_diastolic_bp' => $data['maternal_diastolic_bp'] ?? null,
                'maternal_pulse_bpm' => $data['maternal_pulse_bpm'] ?? null,
                'alert_line_crossed' => $alertLineCrossed,
                'action_line_crossed' => $actionLineCrossed,
                'midwife_remarks' => $data['midwife_remarks'] ?? null,
                'recorded_at' => now(),
            ]);
        });
    }
}
