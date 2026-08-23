<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\StockTransferItem;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class CreateStockTransferAction
{
    /**
     * Dispatch an inter-store stock transfer (Step 1 of Handshake).
     * Deducts quantity from source location balance and writes a TRANSFER_OUT movement.
     */
    public function execute(
        string $sourceLocationId,
        string $destinationLocationId,
        array $items, // array of ['medication_id', 'batch_id', 'quantity']
        ?string $userId = null,
        ?string $notes = null
    ): StockTransfer {
        return DB::transaction(function () use ($sourceLocationId, $destinationLocationId, $items, $userId, $notes) {
            $sourceLocation = InventoryLocation::findOrFail($sourceLocationId);
            $destinationLocation = InventoryLocation::findOrFail($destinationLocationId);
            $tenantId = $sourceLocation->tenant_id;
            $facilityId = $sourceLocation->facility_id;

            $transferNumber = 'TRF-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(3)));

            $transfer = StockTransfer::create([
                'tenant_id' => $tenantId,
                'transfer_number' => $transferNumber,
                'source_location_id' => $sourceLocationId,
                'destination_location_id' => $destinationLocationId,
                'status' => 'Dispatched_In_Transit',
                'dispatched_by' => $userId ?? auth()->id(),
                'dispatched_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($items as $itemData) {
                $medicationId = $itemData['medication_id'];
                $batchId = $itemData['batch_id'];
                $quantity = (int) $itemData['quantity'];

                $batch = InventoryBatch::findOrFail($batchId);
                $medication = MedicationFormulary::findOrFail($medicationId);

                // Check source location balance
                $sourceBalance = InventoryStockBalance::where('location_id', $sourceLocationId)
                    ->where('medication_id', $medicationId)
                    ->where('batch_id', $batchId)
                    ->lockForUpdate()
                    ->first();

                $available = $sourceBalance ? $sourceBalance->quantity_on_hand : 0;
                if ($available < $quantity) {
                    throw InsufficientStockException::forBatch(
                        $medication->generic_name,
                        $batch->batch_number,
                        $quantity,
                        $available
                    );
                }

                // Decrement source location balance
                $sourceBalance->decrement('quantity_on_hand', $quantity);

                // Create Transfer Item
                StockTransferItem::create([
                    'tenant_id' => $tenantId,
                    'stock_transfer_id' => $transfer->id,
                    'medication_id' => $medicationId,
                    'batch_id' => $batchId,
                    'quantity_requested' => $quantity,
                    'quantity_dispatched' => $quantity,
                    'quantity_received' => 0,
                ]);

                // Write immutable stock movement (TRANSFER_OUT)
                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'medication_id' => $medicationId,
                    'batch_id' => $batchId,
                    'movement_type' => 'Transfer_Out',
                    'quantity_change' => -$quantity,
                    'quantity_before' => $available,
                    'quantity_after' => $available - $quantity,
                    'reference_type' => 'StockTransfer',
                    'reference_id' => $transfer->id,
                    'performed_by' => $userId ?? auth()->id(),
                    'notes' => "Dispatched to {$destinationLocation->name} ({$transferNumber})",
                ]);
            }

            return $transfer->load(['sourceLocation', 'destinationLocation', 'items.medication', 'items.batch']);
        });
    }
}
