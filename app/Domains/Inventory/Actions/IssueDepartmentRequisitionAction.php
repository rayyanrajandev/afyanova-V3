<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Models\DepartmentRequisition;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class IssueDepartmentRequisitionAction
{
    /**
     * Storekeeper allocates stock batches, packs items, and dispatches the requisition (In-Transit).
     */
    public function execute(
        string $requisitionId,
        ?array $itemBatchAllocations = null, // array of [requisition_item_id => ['batch_id' => ..., 'quantity_dispatched' => ...]]
        ?string $userId = null,
        ?string $notes = null
    ): DepartmentRequisition {
        return DB::transaction(function () use ($requisitionId, $itemBatchAllocations, $userId, $notes) {
            $requisition = DepartmentRequisition::with(['items.item', 'sourceLocation', 'destinationLocation'])->findOrFail($requisitionId);

            if ($requisition->status !== 'Approved') {
                throw new \InvalidArgumentException("Requisition {$requisition->requisition_number} must be Approved before issuing (Current: {$requisition->status}).");
            }

            $sourceLocationId = $requisition->source_location_id;
            $facilityId = $requisition->facility_id;
            $tenantId = $requisition->tenant_id;

            foreach ($requisition->items as $reqItem) {
                $customAlloc = $itemBatchAllocations[$reqItem->id] ?? null;
                $batchId = $customAlloc['batch_id'] ?? null;
                $qtyToDispatch = isset($customAlloc['quantity_dispatched'])
                    ? (int) $customAlloc['quantity_dispatched']
                    : $reqItem->quantity_approved;

                if ($qtyToDispatch <= 0) {
                    continue;
                }

                // Find active batch with available stock if not explicitly chosen
                if (! $batchId) {
                    $balance = InventoryStockBalance::where('location_id', $sourceLocationId)
                        ->where('quantity_on_hand', '>=', $qtyToDispatch)
                        ->first();
                    $batchId = $balance ? $balance->batch_id : null;
                }

                // Check source balance
                $sourceBalance = InventoryStockBalance::where('location_id', $sourceLocationId)
                    ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
                    ->lockForUpdate()
                    ->first();

                $available = $sourceBalance ? $sourceBalance->quantity_on_hand : 0;
                if ($available < $qtyToDispatch) {
                    throw InsufficientStockException::forLocation(
                        $reqItem->item->name,
                        $requisition->sourceLocation->name,
                        $qtyToDispatch,
                        $available
                    );
                }

                // Decrement source balance
                $sourceBalance->decrement('quantity_on_hand', $qtyToDispatch);

                // Update requisition item
                $reqItem->update([
                    'batch_id' => $batchId,
                    'quantity_dispatched' => $qtyToDispatch,
                ]);

                // Write immutable stock movement (TRANSFER_OUT)
                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'medication_id' => $reqItem->item->medication_id ?? '00000000000000000000000000',
                    'batch_id' => $batchId ?? '00000000000000000000000000',
                    'movement_type' => 'Transfer_Out',
                    'quantity_change' => -$qtyToDispatch,
                    'quantity_before' => $available,
                    'quantity_after' => $available - $qtyToDispatch,
                    'reference_type' => 'DepartmentRequisition',
                    'reference_id' => $requisition->id,
                    'performed_by' => $userId ?? auth()->id(),
                    'notes' => "Store Indent Issue to {$requisition->destinationLocation->name} ({$requisition->requisition_number})",
                ]);
            }

            $requisition->update([
                'status' => 'Dispatched_In_Transit',
                'dispatched_by' => $userId ?? auth()->id(),
                'dispatched_at' => now(),
                'notes' => $notes ? ($requisition->notes."\nDispatch Note: ".$notes) : $requisition->notes,
            ]);

            return $requisition->fresh(['department', 'sourceLocation', 'destinationLocation', 'items.item', 'dispatchedBy']);
        });
    }
}
