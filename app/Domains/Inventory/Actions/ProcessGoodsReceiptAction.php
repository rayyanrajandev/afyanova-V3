<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Billing\Exceptions\LedgerImbalanceException;
use App\Domains\Billing\Models\LedgerAccount;
use App\Domains\Billing\Models\LedgerTransaction;
use App\Domains\Inventory\Models\GoodsReceiptItem;
use App\Domains\Inventory\Models\GoodsReceiptNote;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\PurchaseOrderItem;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ProcessGoodsReceiptAction
{
    public function execute(
        ?string $purchaseOrderId,
        string $supplierId,
        string $facilityId,
        string $locationId,
        array $items,
        string $receivedDate,
        ?string $supplierInvoiceNumber = null,
        ?string $deliveryNoteNumber = null,
        ?string $userId = null,
        ?string $notes = null
    ): GoodsReceiptNote {
        return DB::transaction(function () use (
            $purchaseOrderId, $supplierId, $facilityId, $locationId, $items, $receivedDate,
            $supplierInvoiceNumber, $deliveryNoteNumber, $userId, $notes
        ) {
            $supplier = Supplier::findOrFail($supplierId);
            $location = InventoryLocation::findOrFail($locationId);
            $tenantId = $supplier->tenant_id;
            $userId = $userId ?? auth()->id();
            $grnNumber = 'GRN-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(3)));

            $totalReceivedValue = 0.0;
            foreach ($items as $item) {
                $recQty = (int) ($item['received_quantity'] ?? 0);
                $unitCost = (float) ($item['unit_purchase_cost'] ?? 0);
                $totalReceivedValue += ($recQty * $unitCost);
            }

            $grn = GoodsReceiptNote::create([
                'tenant_id' => $tenantId,
                'grn_number' => $grnNumber,
                'purchase_order_id' => $purchaseOrderId,
                'supplier_id' => $supplierId,
                'facility_id' => $facilityId,
                'location_id' => $locationId,
                'supplier_invoice_number' => $supplierInvoiceNumber,
                'delivery_note_number' => $deliveryNoteNumber,
                'received_date' => $receivedDate,
                'status' => 'Posted_To_Ledger',
                'total_received_value' => $totalReceivedValue,
                'received_by' => $userId,
                'verified_by' => $userId,
                'notes' => $notes,
            ]);

            foreach ($items as $itemData) {
                $medicationId = $itemData['medication_id'];
                $poItemId = $itemData['po_item_id'] ?? null;
                $batchNumber = $itemData['batch_number'];
                $expiryDate = $itemData['expiry_date'];
                $receivedQty = (int) ($itemData['received_quantity'] ?? 0);
                $rejectedQty = (int) ($itemData['rejected_quantity'] ?? 0);
                $unitPurchaseCost = (float) ($itemData['unit_purchase_cost'] ?? 0);
                $unitSellingPrice = (float) ($itemData['unit_selling_price'] ?? ($unitPurchaseCost * 1.35));
                $rejectionReason = $itemData['rejection_reason'] ?? null;

                if ($receivedQty > 0) {
                    // 1. Create InventoryBatch
                    $batch = InventoryBatch::create([
                        'tenant_id' => $tenantId,
                        'facility_id' => $facilityId,
                        'medication_id' => $medicationId,
                        'batch_number' => $batchNumber,
                        'initial_quantity' => $receivedQty,
                        'current_quantity' => $receivedQty,
                        'unit_cost' => $unitPurchaseCost,
                        'unit_selling_price' => $unitSellingPrice,
                        'expiry_date' => $expiryDate,
                        'status' => 'Active',
                    ]);

                    // 2. Create GoodsReceiptItem
                    $grnItem = GoodsReceiptItem::create([
                        'tenant_id' => $tenantId,
                        'goods_receipt_note_id' => $grn->id,
                        'purchase_order_item_id' => $poItemId,
                        'medication_id' => $medicationId,
                        'batch_id' => $batch->id,
                        'batch_number' => $batchNumber,
                        'expiry_date' => $expiryDate,
                        'received_quantity' => $receivedQty,
                        'rejected_quantity' => $rejectedQty,
                        'unit_purchase_cost' => $unitPurchaseCost,
                        'unit_selling_price' => $unitSellingPrice,
                        'total_cost' => $receivedQty * $unitPurchaseCost,
                        'rejection_reason' => $rejectionReason,
                    ]);

                    // 3. Update Multi-Location Stock Balance
                    $stockBalance = InventoryStockBalance::firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'facility_id' => $facilityId,
                            'location_id' => $locationId,
                            'medication_id' => $medicationId,
                            'batch_id' => $batch->id,
                        ],
                        [
                            'quantity_on_hand' => 0,
                            'quantity_reserved' => 0,
                            'reorder_level' => 20,
                            'reorder_quantity' => 100,
                        ]
                    );

                    $qtyBefore = $stockBalance->quantity_on_hand;
                    $stockBalance->increment('quantity_on_hand', $receivedQty);

                    // 4. Update Moving Weighted Average Cost (AVCO) on ItemMaster / MedicationFormulary
                    $itemMaster = ItemMaster::where('medication_id', $medicationId)->first();
                    if ($itemMaster) {
                        $currentStock = InventoryStockBalance::where('medication_id', $medicationId)->sum('quantity_on_hand');
                        $previousStock = max(0, $currentStock - $receivedQty);
                        $currentAvco = floatval($itemMaster->unit_cost_price ?? $unitPurchaseCost);

                        $newAvco = ($currentStock > 0)
                            ? round((($previousStock * $currentAvco) + ($receivedQty * $unitPurchaseCost)) / $currentStock, 2)
                            : $unitPurchaseCost;

                        $itemMaster->update(['unit_cost_price' => $newAvco]);
                    }

                    // 5. Write Immutable Stock Movement (GOODS_RECEIPT)
                    StockMovement::create([
                        'tenant_id' => $tenantId,
                        'facility_id' => $facilityId,
                        'medication_id' => $medicationId,
                        'batch_id' => $batch->id,
                        'movement_type' => 'Goods_Receipt',
                        'quantity_change' => $receivedQty,
                        'quantity_before' => $qtyBefore,
                        'quantity_after' => $qtyBefore + $receivedQty,
                        'reference_type' => 'GoodsReceiptNote',
                        'reference_id' => $grn->id,
                        'performed_by' => $userId,
                        'notes' => "Received from {$supplier->name} ({$grnNumber})",
                    ]);
                }

                // Update PO Item if linked
                if ($poItemId) {
                    $poItem = PurchaseOrderItem::find($poItemId);
                    if ($poItem) {
                        $poItem->increment('received_quantity', $receivedQty);
                    }
                }
            }

            // 6. Post Double-Entry General Ledger Transaction for Accounts Payable & Inventory Asset
            if ($totalReceivedValue > 0) {
                $inventoryAssetAccount = LedgerAccount::firstOrCreate(
                    ['code' => '1300', 'tenant_id' => $tenantId],
                    ['name' => 'Medical & Pharmaceutical Inventory (Asset)', 'type' => 'Asset']
                );

                $accountsPayableAccount = LedgerAccount::firstOrCreate(
                    ['code' => '2000', 'tenant_id' => $tenantId],
                    ['name' => 'Accounts Payable - Suppliers (Liability)', 'type' => 'Liability']
                );

                $transaction = LedgerTransaction::create([
                    'facility_id' => $facilityId,
                    'user_id' => $userId,
                    'reference_type' => 'GoodsReceiptNote',
                    'reference_id' => $grn->id,
                    'description' => "GRN {$grnNumber} from {$supplier->name} (Inv: {$supplierInvoiceNumber})",
                ]);

                // Debit Inventory Asset (Asset increases)
                $transaction->entries()->create([
                    'account_id' => $inventoryAssetAccount->id,
                    'debit' => $totalReceivedValue,
                    'credit' => 0.00,
                ]);

                // Credit Accounts Payable (Liability increases)
                $transaction->entries()->create([
                    'account_id' => $accountsPayableAccount->id,
                    'debit' => 0.00,
                    'credit' => $totalReceivedValue,
                ]);

                // Invariant Check
                $totalDebits = $transaction->entries()->sum('debit');
                $totalCredits = $transaction->entries()->sum('credit');
                if (abs($totalDebits - $totalCredits) > 0.001) {
                    throw LedgerImbalanceException::unbalanced($totalDebits, $totalCredits);
                }
            }

            // Update PO Status if all received
            if ($purchaseOrderId) {
                $po = PurchaseOrder::with('items')->find($purchaseOrderId);
                if ($po) {
                    $allReceived = $po->items->every(fn ($i) => $i->received_quantity >= $i->requested_quantity);
                    $po->update([
                        'status' => $allReceived ? 'Completed' : 'Partially_Received',
                    ]);
                }
            }

            return $grn->load(['supplier', 'facility', 'location', 'items.medication', 'items.batch']);
        });
    }
}
