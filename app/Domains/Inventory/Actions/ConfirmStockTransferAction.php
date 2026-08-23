<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ConfirmStockTransferAction
{
    /**
     * Receive and confirm an in-transit stock transfer (Step 2 of Handshake).
     * Increments quantity at destination location balance and writes a TRANSFER_IN movement.
     */
    public function execute(
        string $transferId,
        ?array $receivedItemQuantities = null, // optional map of [itemId => received_qty]
        ?string $userId = null,
        ?string $notes = null
    ): StockTransfer {
        return DB::transaction(function () use ($transferId, $receivedItemQuantities, $userId, $notes) {
            $transfer = StockTransfer::with(['items', 'sourceLocation', 'destinationLocation'])->findOrFail($transferId);

            if ($transfer->status !== 'Dispatched_In_Transit') {
                throw new \InvalidArgumentException("Transfer {$transfer->transfer_number} is not in transit (Current status: {$transfer->status}).");
            }

            $destinationLocationId = $transfer->destination_location_id;
            $facilityId = $transfer->destinationLocation->facility_id;
            $tenantId = $transfer->tenant_id;

            foreach ($transfer->items as $item) {
                $receivedQty = isset($receivedItemQuantities[$item->id])
                    ? (int) $receivedItemQuantities[$item->id]
                    : $item->quantity_dispatched;

                $discrepancy = $item->quantity_dispatched - $receivedQty;
                $item->update([
                    'quantity_received' => $receivedQty,
                    'discrepancy_reason' => $discrepancy > 0 ? ($notes ?? 'Quantity variance reported upon receipt') : null,
                ]);

                // Find or create stock balance at destination location
                $destBalance = InventoryStockBalance::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'facility_id' => $facilityId,
                        'location_id' => $destinationLocationId,
                        'medication_id' => $item->medication_id,
                        'batch_id' => $item->batch_id,
                    ],
                    [
                        'quantity_on_hand' => 0,
                        'quantity_reserved' => 0,
                        'reorder_level' => 20,
                        'reorder_quantity' => 100,
                    ]
                );

                $qtyBefore = $destBalance->quantity_on_hand;
                $destBalance->increment('quantity_on_hand', $receivedQty);

                // Write immutable stock movement (TRANSFER_IN)
                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'medication_id' => $item->medication_id,
                    'batch_id' => $item->batch_id,
                    'movement_type' => 'Transfer_In',
                    'quantity_change' => $receivedQty,
                    'quantity_before' => $qtyBefore,
                    'quantity_after' => $qtyBefore + $receivedQty,
                    'reference_type' => 'StockTransfer',
                    'reference_id' => $transfer->id,
                    'performed_by' => $userId ?? auth()->id(),
                    'notes' => "Received from {$transfer->sourceLocation->name} ({$transfer->transfer_number})",
                ]);
            }

            $transfer->update([
                'status' => 'Received_Confirmed',
                'received_by' => $userId ?? auth()->id(),
                'received_at' => now(),
                'notes' => $notes ? ($transfer->notes."\nReceipt Note: ".$notes) : $transfer->notes,
            ]);

            return $transfer->fresh(['sourceLocation', 'destinationLocation', 'items.medication', 'items.batch']);
        });
    }
}
