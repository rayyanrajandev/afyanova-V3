<?php

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Actions\ProcessGoodsReceiptAction;
use App\Domains\Inventory\Models\InventoryLocation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Goods Receipt Note posting. Split out of InventoryWorkspaceController
 * (see InventoryRequisitionController's docblock for why).
 */
class InventoryGoodsReceiptController extends Controller
{
    use AuthorizesRequests;

    public function storeGoodsReceipt(Request $request, ProcessGoodsReceiptAction $action): RedirectResponse
    {
        $this->authorize('receiveGoods', [InventoryLocation::class, $request->input('facility_id')]);

        $validated = $request->validate([
            'purchase_order_id' => 'nullable|string',
            'supplier_id' => 'required|string',
            'facility_id' => 'required|string',
            'location_id' => 'required|string',
            'received_date' => 'required|date',
            'supplier_invoice_number' => 'nullable|string',
            'delivery_note_number' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.medication_id' => 'required|string',
            'items.*.po_item_id' => 'nullable|string',
            'items.*.batch_number' => 'required|string',
            'items.*.expiry_date' => 'required|date|after:today',
            'items.*.received_quantity' => 'required|integer|min:1',
            'items.*.rejected_quantity' => 'nullable|integer|min:0',
            'items.*.unit_purchase_cost' => 'required|numeric|min:0',
            'items.*.unit_selling_price' => 'nullable|numeric|min:0',
            'items.*.rejection_reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $action->execute(
            $validated['purchase_order_id'] ?? null,
            $validated['supplier_id'],
            $validated['facility_id'],
            $validated['location_id'],
            $validated['items'],
            $validated['received_date'],
            $validated['supplier_invoice_number'] ?? null,
            $validated['delivery_note_number'] ?? null,
            auth()->id(),
            $validated['notes'] ?? null
        );

        return back()->with('success', 'Goods Receipt Note (GRN) posted to inventory ledger successfully.');
    }
}
