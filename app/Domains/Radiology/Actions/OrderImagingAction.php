<?php

namespace App\Domains\Radiology\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Radiology\Models\RadiologyOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderImagingAction
{
    public function execute(Encounter $encounter, array $data): RadiologyOrder
    {
        $patient = $encounter->patient;
        if ($patient?->isDeceased()) {
            throw new InvalidArgumentException('Cannot order diagnostic imaging for a deceased patient.');
        }
        if ($patient?->isMerged()) {
            throw new InvalidArgumentException('Cannot order imaging for a merged patient record.');
        }

        return DB::transaction(function () use ($encounter, $data) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $encounter->tenant_id;
            $facilityId = $encounter->facility_id ?? app(FacilityContext::class)->getFacilityId();

            $orderNumber = 'RAD-'.date('Y').'-'.strtoupper(Str::random(6));

            return RadiologyOrder::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'ordering_doctor_id' => auth()->id() ?? $encounter->provider_id,
                'order_number' => $orderNumber,
                'modality' => $data['modality'], // X-Ray, Ultrasound, CT Scan, MRI, Echo
                'procedure_name' => $data['procedure_name'],
                'body_site' => $data['body_site'] ?? null,
                'clinical_indication' => $data['clinical_indication'] ?? null,
                'priority' => $data['priority'] ?? 'Routine',
                'status' => 'Ordered',
                'ordered_at' => now(),
            ]);
        });
    }
}
