<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class DispenseStockAction
{
    /**
     * Executes atomic physical stock decrement for clinical dispensing.
     * Enforces Zero-Negative Stock invariant and FEFO batch selection.
     */
    public function execute(
        string $medicationId,
        int $quantity,
        ?string $locationId = null,
        ?string $batchId = null,
        ?string $referenceType = 'DispenseEvent',
        ?string $referenceId = null,
        ?string $userId = null
    ): array {
        return DB::transaction(function () use (
            $medicationId, $quantity, $locationId, $batchId, $referenceType, $referenceId, $userId
        ) {
            $medication = MedicationFormulary::findOrFail($medicationId);
            $tenantId = $medication->tenant_id;

            // Resolve location (Default to first dispensing-enabled location if not specified)
            if (! $locationId) {
                $location = InventoryLocation::where('tenant_id', $tenantId)
                    ->where('is_dispensing_enabled', true)
                    ->first();
                $locationId = $location ? $location->id : null;
            } else {
                $location = InventoryLocation::find($locationId);
            }

            $facilityId = $location ? $location->facility_id : auth()->user()?->facility_id;

            $dispensedBatches = [];
            $remainingToDispense = $quantity;

            if ($batchId) {
                // Specific batch specified
                $batch = InventoryBatch::where('id', $batchId)->lockForUpdate()->firstOrFail();
                if ($batch->current_quantity < $quantity) {
                    throw InsufficientStockException::forBatch(
                        $medication->generic_name,
                        $batch->batch_number,
                        $quantity,
                        $batch->current_quantity
                    );
                }

                $qtyBefore = $batch->current_quantity;
                $batch->decrement('current_quantity', $quantity);

                // Update location balance if location exists
                if ($locationId) {
                    $balance = InventoryStockBalance::where('location_id', $locationId)
                        ->where('medication_id', $medicationId)
                        ->where('batch_id', $batchId)
                        ->first();
                    if ($balance) {
                        $balance->decrement('quantity_on_hand', min($balance->quantity_on_hand, $quantity));
                    }
                }

                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'medication_id' => $medicationId,
                    'batch_id' => $batchId,
                    'movement_type' => 'Dispensed',
                    'quantity_change' => -$quantity,
                    'quantity_before' => $qtyBefore,
                    'quantity_after' => $qtyBefore - $quantity,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'performed_by' => $userId ?? auth()->id(),
                    'notes' => 'Clinical Prescription Dispensation',
                ]);

                $dispensedBatches[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $quantity,
                ];
            } else {
                // FEFO Auto-allocation across active non-expired batches
                $availableBatches = InventoryBatch::where('tenant_id', $tenantId)
                    ->where('medication_id', $medicationId)
                    ->where('status', 'Active')
                    ->where('current_quantity', '>', 0)
                    ->where('expiry_date', '>', now())
                    ->orderBy('expiry_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $totalAvailable = $availableBatches->sum('current_quantity');
                if ($totalAvailable < $quantity) {
                    throw new InsufficientStockException(
                        "Insufficient overall stock for {$medication->generic_name}. Requested {$quantity}, but only {$totalAvailable} available."
                    );
                }

                foreach ($availableBatches as $b) {
                    if ($remainingToDispense <= 0) {
                        break;
                    }

                    $take = min($b->current_quantity, $remainingToDispense);
                    $qtyBefore = $b->current_quantity;
                    $b->decrement('current_quantity', $take);
                    $remainingToDispense -= $take;

                    if ($locationId) {
                        $balance = InventoryStockBalance::where('location_id', $locationId)
                            ->where('medication_id', $medicationId)
                            ->where('batch_id', $b->id)
                            ->first();
                        if ($balance) {
                            $balance->decrement('quantity_on_hand', min($balance->quantity_on_hand, $take));
                        }
                    }

                    StockMovement::create([
                        'tenant_id' => $tenantId,
                        'facility_id' => $facilityId,
                        'medication_id' => $medicationId,
                        'batch_id' => $b->id,
                        'movement_type' => 'Dispensed',
                        'quantity_change' => -$take,
                        'quantity_before' => $qtyBefore,
                        'quantity_after' => $qtyBefore - $take,
                        'reference_type' => $referenceType,
                        'reference_id' => $referenceId,
                        'performed_by' => $userId ?? auth()->id(),
                        'notes' => 'FEFO Prescription Dispensation',
                    ]);

                    $dispensedBatches[] = [
                        'batch_id' => $b->id,
                        'batch_number' => $b->batch_number,
                        'quantity' => $take,
                    ];
                }
            }

            return $dispensedBatches;
        });
    }
}
