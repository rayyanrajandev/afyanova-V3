<?php

namespace App\Domains\Radiology\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Radiology\Models\RadiologyReport;
use Illuminate\Support\Facades\DB;

class SignRadiologyReportAction
{
    public function execute(RadiologyOrder $order, array $data): RadiologyReport
    {
        return DB::transaction(function () use ($order, $data) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $order->tenant_id;
            $facilityId = $order->facility_id ?? app(FacilityContext::class)->getFacilityId();
            $radiologistId = auth()->id() ?? $data['radiologist_id'];

            $report = RadiologyReport::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'radiology_order_id' => $order->id,
                'radiology_study_id' => $data['radiology_study_id'] ?? null,
                'patient_id' => $order->patient_id,
                'radiologist_id' => $radiologistId,
                'findings' => $data['findings'],
                'impression' => $data['impression'],
                'recommendations' => $data['recommendations'] ?? null,
                'is_critical_finding' => $data['is_critical_finding'] ?? false,
                'critical_notified_at' => ($data['is_critical_finding'] ?? false) ? now() : null,
                'is_signed' => true,
                'signed_at' => now(),
            ]);

            $order->update(['status' => 'Reported']);

            return $report->fresh(['order', 'radiologist', 'patient']);
        });
    }
}
