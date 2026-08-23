<?php

namespace App\Domains\Clinical\Actions;

use App\Domains\Clinical\Events\CriticalVitalRecordedEvent;
use App\Domains\Clinical\Models\ClinicalVital;
use App\Domains\Clinical\Services\VitalsTelemetryService;
use App\Domains\Patient\Models\Patient;
use InvalidArgumentException;

class RecordVitalsAction
{
    public function __construct(
        protected VitalsTelemetryService $telemetryService
    ) {}

    public function execute(array $data): ClinicalVital
    {
        $patient = Patient::findOrFail((string) $data['patient_id']);

        if ($patient->isDeceased()) {
            throw new InvalidArgumentException("Cannot record vitals. Patient {$patient->first_name} {$patient->last_name} is recorded as Deceased.");
        }

        if ($patient->isMerged()) {
            throw new InvalidArgumentException("Cannot record vitals. Patient {$patient->first_name} {$patient->last_name} has been merged into {$patient->merged_into_patient_id}.");
        }

        $this->validatePhysiologicRanges($data);

        $vital = ClinicalVital::create([
            'encounter_id' => $data['encounter_id'],
            'patient_id' => $data['patient_id'],
            'recorded_by' => auth()->id() ?? $data['recorded_by'] ?? null,
            'temperature_c' => $data['temperature_c'] ?? null,
            'heart_rate' => $data['heart_rate'] ?? null,
            'systolic_bp' => $data['systolic_bp'] ?? null,
            'diastolic_bp' => $data['diastolic_bp'] ?? null,
            'respiratory_rate' => $data['respiratory_rate'] ?? null,
            'oxygen_saturation' => $data['oxygen_saturation'] ?? null,
            'weight_kg' => $data['weight_kg'] ?? null,
            'height_cm' => $data['height_cm'] ?? null,
            'bmi' => $this->calculateBmi($data['weight_kg'] ?? null, $data['height_cm'] ?? null),
            'notes' => $data['notes'] ?? null,
        ]);

        // Evaluate physiological telemetry for critical / panic values
        $panicFlags = $this->telemetryService->evaluatePanicThresholds($data);
        if (! empty($panicFlags)) {
            event(new CriticalVitalRecordedEvent($vital, $panicFlags, auth()->id()));
        }

        return $vital;
    }

    protected function validatePhysiologicRanges(array $data): void
    {
        if (isset($data['temperature_c']) && ($data['temperature_c'] < 20 || $data['temperature_c'] > 45)) {
            throw new InvalidArgumentException('Temperature must be between 20°C and 45°C.');
        }

        if (isset($data['systolic_bp'], $data['diastolic_bp']) && $data['systolic_bp'] <= $data['diastolic_bp']) {
            throw new InvalidArgumentException('Systolic BP must be greater than Diastolic BP.');
        }
    }

    protected function calculateBmi(?float $weight, ?float $heightCm): ?float
    {
        if (! $weight || ! $heightCm) {
            return null;
        }
        $heightM = $heightCm / 100;

        return round($weight / ($heightM * $heightM), 2);
    }
}
