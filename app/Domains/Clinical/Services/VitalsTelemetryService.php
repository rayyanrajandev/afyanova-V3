<?php

namespace App\Domains\Clinical\Services;

/**
 * VitalsTelemetryService
 *
 * Evaluates patient physiological measurements against international
 * critical/panic boundaries (NEWS2 / MEWS guidelines) to trigger alerts.
 */
class VitalsTelemetryService
{
    /**
     * Evaluate vital signs for life-threatening panic / critical thresholds.
     *
     * @return array<string, array{parameter: string, value: float|int, severity: string, message: string}>
     */
    public function evaluatePanicThresholds(array $vitals): array
    {
        $flags = [];

        // 1. Oxygen Saturation (SpO2)
        if (isset($vitals['oxygen_saturation'])) {
            $spo2 = floatval($vitals['oxygen_saturation']);
            if ($spo2 < 88.0) {
                $flags['oxygen_saturation'] = [
                    'parameter' => 'Oxygen Saturation',
                    'value' => $spo2,
                    'severity' => 'Critical',
                    'message' => "Severe Hypoxemia: SpO2 {$spo2}% (Panic threshold < 88%). Requires urgent oxygen therapy.",
                ];
            } elseif ($spo2 < 92.0) {
                $flags['oxygen_saturation'] = [
                    'parameter' => 'Oxygen Saturation',
                    'value' => $spo2,
                    'severity' => 'Warning',
                    'message' => "Hypoxia Warning: SpO2 {$spo2}% (Normal > 94%).",
                ];
            }
        }

        // 2. Systolic Blood Pressure (SBP)
        if (isset($vitals['systolic_bp'])) {
            $sbp = intval($vitals['systolic_bp']);
            if ($sbp >= 190) {
                $flags['systolic_bp'] = [
                    'parameter' => 'Systolic Blood Pressure',
                    'value' => $sbp,
                    'severity' => 'Critical',
                    'message' => "Hypertensive Crisis: Systolic BP {$sbp} mmHg (Panic threshold >= 190 mmHg). Risk of end-organ damage.",
                ];
            } elseif ($sbp < 75) {
                $flags['systolic_bp'] = [
                    'parameter' => 'Systolic Blood Pressure',
                    'value' => $sbp,
                    'severity' => 'Critical',
                    'message' => "Severe Hypotension / Shock: Systolic BP {$sbp} mmHg (Panic threshold < 75 mmHg).",
                ];
            }
        }

        // 3. Heart Rate (HR)
        if (isset($vitals['heart_rate'])) {
            $hr = intval($vitals['heart_rate']);
            if ($hr >= 150) {
                $flags['heart_rate'] = [
                    'parameter' => 'Heart Rate',
                    'value' => $hr,
                    'severity' => 'Critical',
                    'message' => "Severe Tachycardia: Heart rate {$hr} bpm (Panic threshold >= 150 bpm).",
                ];
            } elseif ($hr < 40) {
                $flags['heart_rate'] = [
                    'parameter' => 'Heart Rate',
                    'value' => $hr,
                    'severity' => 'Critical',
                    'message' => "Severe Bradycardia: Heart rate {$hr} bpm (Panic threshold < 40 bpm).",
                ];
            }
        }

        // 4. Respiratory Rate (RR)
        if (isset($vitals['respiratory_rate'])) {
            $rr = intval($vitals['respiratory_rate']);
            if ($rr >= 35) {
                $flags['respiratory_rate'] = [
                    'parameter' => 'Respiratory Rate',
                    'value' => $rr,
                    'severity' => 'Critical',
                    'message' => "Severe Tachypnea: Respiratory rate {$rr}/min (Panic threshold >= 35/min).",
                ];
            } elseif ($rr < 8) {
                $flags['respiratory_rate'] = [
                    'parameter' => 'Respiratory Rate',
                    'value' => $rr,
                    'severity' => 'Critical',
                    'message' => "Severe Bradypnea / Respiratory Failure: Respiratory rate {$rr}/min (Panic threshold < 8/min).",
                ];
            }
        }

        // 5. Body Temperature
        if (isset($vitals['temperature_c'])) {
            $temp = floatval($vitals['temperature_c']);
            if ($temp >= 40.0) {
                $flags['temperature_c'] = [
                    'parameter' => 'Body Temperature',
                    'value' => $temp,
                    'severity' => 'Critical',
                    'message' => "Hyperpyrexia: Temperature {$temp}°C (Panic threshold >= 40.0°C).",
                ];
            } elseif ($temp < 35.0) {
                $flags['temperature_c'] = [
                    'parameter' => 'Body Temperature',
                    'value' => $temp,
                    'severity' => 'Critical',
                    'message' => "Severe Hypothermia: Temperature {$temp}°C (Panic threshold < 35.0°C).",
                ];
            }
        }

        return $flags;
    }
}
