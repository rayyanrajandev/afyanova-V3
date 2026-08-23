<?php

namespace App\Domains\Radiology\Actions;

use App\Domains\Radiology\Models\RadiologyReport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AmendRadiologyReportAction
{
    public function execute(RadiologyReport $originalReport, array $data): RadiologyReport
    {
        if (! $originalReport->is_signed) {
            throw new InvalidArgumentException('Cannot amend an unsigned report. Edit the draft directly.');
        }

        if ($originalReport->is_deprecated) {
            throw new InvalidArgumentException('Cannot amend an already deprecated report.');
        }

        if (empty($data['amendment_reason']) || strlen(trim($data['amendment_reason'])) < 10) {
            throw new InvalidArgumentException('A clinical amendment reason of at least 10 characters is mandatory.');
        }

        return DB::transaction(function () use ($originalReport, $data) {
            // Deprecate original report
            RadiologyReport::withFinalizedMutation(function () use ($originalReport) {
                $originalReport->update(['is_deprecated' => true]);
            });

            // Create new amendment record
            return RadiologyReport::create([
                'tenant_id' => $originalReport->tenant_id,
                'facility_id' => $originalReport->facility_id,
                'radiology_order_id' => $originalReport->radiology_order_id,
                'radiology_study_id' => $originalReport->radiology_study_id,
                'patient_id' => $originalReport->patient_id,
                'radiologist_id' => auth()->id() ?? $originalReport->radiologist_id,
                'findings' => $data['findings'] ?? $originalReport->findings,
                'impression' => $data['impression'] ?? $originalReport->impression,
                'recommendations' => $data['recommendations'] ?? $originalReport->recommendations,
                'is_critical_finding' => $data['is_critical_finding'] ?? $originalReport->is_critical_finding,
                'is_signed' => true,
                'signed_at' => now(),
                'is_amendment' => true,
                'amended_report_id' => $originalReport->id,
                'amendment_reason' => $data['amendment_reason'],
            ]);
        });
    }
}
