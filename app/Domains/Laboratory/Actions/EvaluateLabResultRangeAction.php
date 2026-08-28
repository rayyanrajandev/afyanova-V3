<?php

namespace App\Domains\Laboratory\Actions;

use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Laboratory\Models\LabTestRange;
use App\Domains\Patient\Models\Patient;
use Carbon\Carbon;

class EvaluateLabResultRangeAction
{
    /**
     * Evaluate numeric or qualitative lab result value against stratified reference ranges.
     *
     * @return array{flag: string, range_text: ?string, is_critical: bool}
     */
    public function execute(LabOrderItem $item, float|string $resultValue, ?Patient $patient = null): array
    {
        $patient = $patient ?? $item->labOrder?->patient;
        $gender = $patient?->gender ? ucfirst(strtolower($patient->gender)) : 'All';
        $dob = $patient?->dob ? Carbon::parse($patient->dob) : null;
        // diffInDays() returns a float (sub-day precision) — age_min_days/
        // age_max_days are integer columns, and Postgres rejects a float
        // parameter bound against one (SQLite silently coerces it, which is
        // why this only ever surfaced running the suite against Postgres).
        $ageDays = $dob ? (int) $dob->diffInDays(now()) : 3650; // Default ~10y if unknown

        $range = LabTestRange::where('lab_test_id', $item->lab_test_id)
            ->where(function ($q) use ($gender) {
                $q->where('gender', $gender)->orWhere('gender', 'All');
            })
            ->where('age_min_days', '<=', $ageDays)
            ->where('age_max_days', '>=', $ageDays)
            ->first();

        if (! $range) {
            return [
                'flag' => 'Normal',
                'range_text' => null,
                'is_critical' => false,
            ];
        }

        if (is_numeric($resultValue)) {
            $val = floatval($resultValue);

            // Critical evaluation
            if ($range->critical_low !== null && $val <= $range->critical_low) {
                return [
                    'flag' => 'Critical Low',
                    'range_text' => "{$range->normal_min} - {$range->normal_max} {$range->unit}",
                    'is_critical' => true,
                ];
            }

            if ($range->critical_high !== null && $val >= $range->critical_high) {
                return [
                    'flag' => 'Critical High',
                    'range_text' => "{$range->normal_min} - {$range->normal_max} {$range->unit}",
                    'is_critical' => true,
                ];
            }

            // Normal / Abnormal evaluation
            if (($range->normal_min !== null && $val < $range->normal_min) ||
                ($range->normal_max !== null && $val > $range->normal_max)) {
                return [
                    'flag' => $val < $range->normal_min ? 'Low' : 'High',
                    'range_text' => "{$range->normal_min} - {$range->normal_max} {$range->unit}",
                    'is_critical' => false,
                ];
            }

            return [
                'flag' => 'Normal',
                'range_text' => "{$range->normal_min} - {$range->normal_max} {$range->unit}",
                'is_critical' => false,
            ];
        }

        // Qualitative match
        if ($range->textual_normal_range) {
            $isMatch = strcasecmp(trim((string) $resultValue), trim($range->textual_normal_range)) === 0;

            return [
                'flag' => $isMatch ? 'Normal' : 'Abnormal',
                'range_text' => $range->textual_normal_range,
                'is_critical' => ! $isMatch && in_array(strtolower((string) $resultValue), ['positive', 'reactive']),
            ];
        }

        return [
            'flag' => 'Normal',
            'range_text' => null,
            'is_critical' => false,
        ];
    }
}
