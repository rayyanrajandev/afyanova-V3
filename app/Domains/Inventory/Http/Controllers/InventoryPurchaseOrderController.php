<?php

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Actions\ApprovePurchaseOrderAction;
use App\Domains\Inventory\Actions\CreatePurchaseOrderAction;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\PurchaseOrder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Purchase order creation/approval. Split out of
 * InventoryWorkspaceController (see InventoryRequisitionController's
 * docblock for why).
 */
class InventoryPurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    public function storePurchaseOrder(Request $request, CreatePurchaseOrderAction $action): RedirectResponse
    {
        $this->authorize('createPurchaseOrder', [InventoryLocation::class, $request->input('facility_id')]);

        $validated = $request->validate([
            'supplier_id' => 'required|string',
            'facility_id' => 'required|string',
            'destination_location_id' => 'nullable|string',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'items' => 'required|array|min:1',
            'items.*.medication_id' => 'required|string',
            'items.*.requested_quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $action->execute(
            $validated['supplier_id'],
            $validated['facility_id'],
            $validated['destination_location_id'] ?? null,
            $validated['items'],
            $validated['order_date'],
            $validated['expected_delivery_date'] ?? null,
            auth()->id(),
            $validated['notes'] ?? null
        );

        return back()->with('success', 'Purchase order created and submitted successfully.');
    }

    public function approvePurchaseOrder(string $id, ApprovePurchaseOrderAction $action): RedirectResponse
    {
        $order = PurchaseOrder::findOrFail($id);
        $this->authorize('approvePurchaseOrder', [InventoryLocation::class, $order->facility_id]);

        $action->execute($id, auth()->id());

        return back()->with('success', 'Purchase order approved successfully.');
    }
}
