<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\DepartmentRequisition;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ConfirmDepartmentRequisitionAction
{
    /**
     * Ward nurse or HOD confirms physical receipt of goods into Ward Cabinet (Handshake Step 2).
     */
    public function execute(
        string $requisitionId,
        ?array $receivedQuantities = null, // array of [requisition_item_id => quantity_received]
        ?string $userId = null,
        ?string $notes = null
    ): DepartmentRequisition {
        return DB::transaction(function () use ($requisitionId, $receivedQuantities, $userId, $notes) {
            $requisition = DepartmentRequisition::with(['items.item', 'sourceLocation', 'destinationLocation'])->findOrFail($requisitionId);

            if ($requisition->status !== 'Dispatched_In_Transit') {
                throw new \InvalidArgumentException("Requisition {$requisition->requisition_number} is not in transit (Current: {$requisition->status}).");
            }

            $destinationLocationId = $requisition->destination_location_id;
            $facilityId = $requisition->facility_id;
            $tenantId = $requisition->tenant_id;

            foreach ($requisition->items as $reqItem) {
                $qtyReceived = isset($receivedQuantities[$reqItem->id])
                    ? (int) $receivedQuantities[$reqItem->id]
                    : $reqItem->quantity_dispatched;

                $discrepancy = $reqItem->quantity_dispatched - $qtyReceived;
                $reqItem->update([
                    'quantity_received' => $qtyReceived,
                    'discrepancy_reason' => $discrepancy > 0 ? ($notes ?? 'Delivery count discrepancy') : null,
                ]);

                // Increment destination balance
                $destBalance = InventoryStockBalance::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'facility_id' => $facilityId,
                        'location_id' => $destinationLocationId,
                        'medication_id' => $reqItem->item->medication_id ?? '00000000000000000000000000',
                        'batch_id' => $reqItem->batch_id,
                    ],
                    [
                        'quantity_on_hand' => 0,
                        'quantity_reserved' => 0,
                        'reorder_level' => 10,
                        'reorder_quantity' => 50,
                    ]
                );

                $qtyBefore = $destBalance->quantity_on_hand;
                $destBalance->increment('quantity_on_hand', $qtyReceived);

                // Write immutable stock movement (TRANSFER_IN)
                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'medication_id' => $reqItem->item->medication_id ?? '00000000000000000000000000',
                    'batch_id' => $reqItem->batch_id ?? '00000000000000000000000000',
                    'movement_type' => 'Transfer_In',
                    'quantity_change' => $qtyReceived,
                    'quantity_before' => $qtyBefore,
                    'quantity_after' => $qtyBefore + $qtyReceived,
                    'reference_type' => 'DepartmentRequisition',
                    'reference_id' => $requisition->id,
                    'performed_by' => $userId ?? auth()->id(),
                    'notes' => "Store Indent Intake from {$requisition->sourceLocation->name} ({$requisition->requisition_number})",
                ]);
            }

            $requisition->update([
                'status' => 'Received_Confirmed',
                'received_by' => $userId ?? auth()->id(),
                'received_at' => now(),
                'notes' => $notes ? ($requisition->notes."\nReceipt Note: ".$notes) : $requisition->notes,
            ]);

            return $requisition->fresh(['department', 'sourceLocation', 'destinationLocation', 'items.item', 'receivedBy']);
        });
    }
}
