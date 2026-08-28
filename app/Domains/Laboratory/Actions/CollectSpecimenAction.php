<?php

namespace App\Domains\Laboratory\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Laboratory\Models\LabSpecimen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CollectSpecimenAction
{
    public function execute(LabOrderItem $item, ?string $barcode = null, ?string $technicianRemarks = null, string $sampleType = 'Blood', ?string $containerType = 'EDTA Tube'): LabOrderItem
    {
        $item->load(['labOrder.patient', 'labOrder.encounter']);
        $patient = $item->labOrder?->patient;

        if ($patient?->isDeceased()) {
            throw new InvalidArgumentException('Cannot collect laboratory specimen for a deceased patient.');
        }
        if ($patient?->isMerged()) {
            throw new InvalidArgumentException('Cannot collect specimen for a merged patient record.');
        }

        return DB::transaction(function () use ($item, $barcode, $technicianRemarks, $sampleType, $containerType, $patient) {
            $specimenBarcode = $barcode ?: ('LAB-'.date('Y').'-'.strtoupper(Str::random(6)));
            $userId = auth()->id() ?: $item->performed_by_id;
            $tenantId = app(TenantContext::class)->getTenantId() ?? $item->tenant_id;
            $facilityId = $item->labOrder?->encounter?->facility_id ?? app(FacilityContext::class)->getFacilityId();

            if (! $facilityId) {
                throw new InvalidArgumentException('Unable to determine the facility for this specimen — no encounter and no facility context.');
            }

            $item->update([
                'status' => 'Sample Collected',
                'specimen_barcode' => $specimenBarcode,
                'technician_remarks' => $technicianRemarks ?: $item->technician_remarks,
                'performed_by_id' => $userId,
            ]);

            $order = $item->labOrder;
            if ($order && ($order->status === 'Ordered' || ! $order->collected_at)) {
                $order->update([
                    'status' => 'Sample Collected',
                    'collected_at' => now(),
                ]);
            }

            if ($patient && $order) {
                LabSpecimen::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'lab_order_id' => $order->id,
                    'patient_id' => $patient->id,
                    'collected_by' => $userId,
                    'accession_number' => $specimenBarcode,
                    'sample_type' => $sampleType,
                    'container_type' => $containerType,
                    'status' => 'Collected',
                    'collected_at' => now(),
                ]);
            }

            // Real-time Inventory Consumable Depletion: Deduct 1 unit from bench stock balance
            $inventoryItemId = $item->labTest?->inventory_item_id;
            if ($inventoryItemId) {
                $stockBalance = InventoryStockBalance::where('facility_id', $facilityId)
                    ->where('medication_id', $inventoryItemId)
                    ->where('quantity_on_hand', '>', 0)
                    ->orderBy('quantity_on_hand', 'desc')
                    ->first();

                if ($stockBalance) {
                    $stockBalance->decrement('quantity_on_hand', 1);
                }
            }

            return $item->fresh(['labTest.inventoryItem', 'labOrder.patient', 'performedBy']);
        });
    }
}
