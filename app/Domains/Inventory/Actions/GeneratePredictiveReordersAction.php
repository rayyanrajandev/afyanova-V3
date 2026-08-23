<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inpatient\Models\MedicationAdministrationRecord;
use App\Domains\Inventory\Models\DepartmentRequisitionItem;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\PurchaseOrderItem;
use App\Domains\Inventory\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeneratePredictiveReordersAction
{
    public function execute(string $tenantId, string $facilityId, bool $createPurchaseOrders = true): array
    {
        return DB::transaction(function () use ($tenantId, $facilityId, $createPurchaseOrders) {
            $items = ItemMaster::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get();

            $mainStore = InventoryLocation::where('tenant_id', $tenantId)
                ->where('facility_id', $facilityId)
                ->first();

            $suppliers = Supplier::where('tenant_id', $tenantId)->get();
            $defaultSupplier = $suppliers->first();

            if (! $defaultSupplier) {
                $defaultSupplier = Supplier::create([
                    'tenant_id' => $tenantId,
                    'name' => 'Harleys Pharma Tanzania Ltd',
                    'code' => 'SUP-HARLEYS-01',
                    'payment_terms' => 'Net30',
                    'is_active' => true,
                ]);
            }

            $thirtyDaysAgo = now()->subDays(30);
            $reorderRecommendations = [];
            $itemsNeedingReorder = [];

            foreach ($items as $item) {
                // 1. Calculate 30-Day Historical Consumption
                $marDoses = (float) MedicationAdministrationRecord::where('tenant_id', $tenantId)
                    ->where('administered_at', '>=', $thirtyDaysAgo)
                    ->where(function ($q) use ($item) {
                        $q->where('item_master_id', $item->id);
                        if (! empty($item->medication_id)) {
                            $q->orWhere('medication_id', $item->medication_id);
                        }
                    })
                    ->where('status', 'Administered')
                    ->sum('dose_quantity');

                $requisitionIssues = (float) DepartmentRequisitionItem::where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->where('item_id', $item->id)
                    ->sum('quantity_issued');

                $totalConsumption30d = $marDoses + $requisitionIssues;
                $adc = $totalConsumption30d > 0 ? round($totalConsumption30d / 30, 2) : 1.0; // minimum baseline 1.0/day
                $leadTimeDays = 7; // standard local supplier lead time
                $safetyStock = max($item->safety_stock ?: 10, (int) ceil($adc * 3));
                $reorderPoint = (int) ceil(($adc * $leadTimeDays) + $safetyStock);

                // 2. Current Stock on Hand (SOH)
                $currentStockOnHand = (int) InventoryStockBalance::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($item) {
                        $q->where('item_id', $item->id)
                            ->orWhere('medication_id', $item->id);
                        if (! empty($item->medication_id)) {
                            $q->orWhere('medication_id', $item->medication_id);
                        }
                    })
                    ->sum('quantity_on_hand');

                $daysRemaining = $adc > 0 ? (int) floor($currentStockOnHand / $adc) : 999;
                $isBelowReorderPoint = $currentStockOnHand <= $reorderPoint;

                $suggestedOrderQty = max(
                    $item->reorder_level ?: 50,
                    (int) ceil(($adc * 30) - $currentStockOnHand)
                );

                $recommendation = [
                    'item_id' => $item->id,
                    'medication_id' => $item->medication_id ?: $item->id,
                    'item_code' => $item->item_code,
                    'name' => $item->name,
                    'category' => $item->category,
                    'unit_cost' => (float) $item->unit_cost_price,
                    'current_stock' => $currentStockOnHand,
                    'adc' => $adc,
                    'days_remaining' => $daysRemaining,
                    'reorder_point' => $reorderPoint,
                    'safety_stock' => $safetyStock,
                    'suggested_quantity' => $suggestedOrderQty,
                    'is_critical' => $daysRemaining <= 3,
                    'is_below_rop' => $isBelowReorderPoint,
                ];

                $reorderRecommendations[] = $recommendation;

                if ($isBelowReorderPoint) {
                    $itemsNeedingReorder[] = $recommendation;
                }
            }

            // 3. Generate Draft Purchase Orders if requested
            $createdPurchaseOrders = [];

            if ($createPurchaseOrders && ! empty($itemsNeedingReorder)) {
                $poNumber = 'PO-AUTO-'.date('Y').'-'.strtoupper(Str::random(5));
                $subtotal = 0.0;

                foreach ($itemsNeedingReorder as $rec) {
                    $subtotal += $rec['suggested_quantity'] * $rec['unit_cost'];
                }

                $po = PurchaseOrder::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'destination_location_id' => $mainStore?->id,
                    'supplier_id' => $defaultSupplier->id,
                    'po_number' => $poNumber,
                    'order_date' => now()->toDateString(),
                    'expected_delivery_date' => now()->addDays(7)->toDateString(),
                    'status' => 'Submitted',
                    'subtotal' => $subtotal,
                    'tax_amount' => 0.00,
                    'total_amount' => $subtotal,
                    'currency' => 'TZS',
                    'notes' => 'Auto-generated replenishment order based on 30-day ADC run-rate and safety stock triggers.',
                ]);

                foreach ($itemsNeedingReorder as $rec) {
                    PurchaseOrderItem::create([
                        'tenant_id' => $tenantId,
                        'purchase_order_id' => $po->id,
                        'medication_id' => $rec['medication_id'],
                        'requested_quantity' => $rec['suggested_quantity'],
                        'unit_cost' => $rec['unit_cost'],
                        'total_cost' => $rec['suggested_quantity'] * $rec['unit_cost'],
                    ]);
                }

                $createdPurchaseOrders[] = $po->fresh(['supplier', 'items']);
            }

            return [
                'recommendations' => $reorderRecommendations,
                'items_needing_reorder_count' => count($itemsNeedingReorder),
                'purchase_orders_created' => $createdPurchaseOrders,
            ];
        });
    }
}
