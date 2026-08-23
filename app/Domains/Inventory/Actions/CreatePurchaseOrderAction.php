<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\PurchaseOrderItem;
use App\Domains\Inventory\Models\Supplier;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrderAction
{
    public function execute(
        string $supplierId,
        string $facilityId,
        ?string $destinationLocationId,
        array $items, // array of ['medication_id', 'requested_quantity', 'unit_cost']
        string $orderDate,
        ?string $expectedDeliveryDate = null,
        ?string $userId = null,
        ?string $notes = null
    ): PurchaseOrder {
        return DB::transaction(function () use (
            $supplierId, $facilityId, $destinationLocationId, $items, $orderDate, $expectedDeliveryDate, $userId, $notes
        ) {
            $supplier = Supplier::findOrFail($supplierId);
            $tenantId = $supplier->tenant_id;
            $poNumber = 'PO-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(3)));

            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += ((int) $item['requested_quantity']) * ((float) $item['unit_cost']);
            }
            $tax = 0.0; // standard zero-rated / tax exempt for essential pharmaceuticals
            $total = $subtotal + $tax;

            $po = PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'po_number' => $poNumber,
                'supplier_id' => $supplierId,
                'facility_id' => $facilityId,
                'destination_location_id' => $destinationLocationId,
                'order_date' => $orderDate,
                'expected_delivery_date' => $expectedDeliveryDate,
                'status' => 'Submitted',
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'currency' => 'TZS',
                'ordered_by' => $userId ?? auth()->id(),
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $qty = (int) $item['requested_quantity'];
                $cost = (float) $item['unit_cost'];
                PurchaseOrderItem::create([
                    'tenant_id' => $tenantId,
                    'purchase_order_id' => $po->id,
                    'medication_id' => $item['medication_id'],
                    'requested_quantity' => $qty,
                    'received_quantity' => 0,
                    'unit_cost' => $cost,
                    'total_cost' => $qty * $cost,
                ]);
            }

            return $po->load(['supplier', 'facility', 'destinationLocation', 'items.medication']);
        });
    }
}
