<?php

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Actions\ConfirmStockTransferAction;
use App\Domains\Inventory\Actions\CreateStockTransferAction;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\StockTransfer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Inter-location stock transfer dispatch/confirm. Split out of
 * InventoryWorkspaceController (see InventoryRequisitionController's
 * docblock for why).
 */
class InventoryTransferController extends Controller
{
    use AuthorizesRequests;

    public function storeTransfer(Request $request, CreateStockTransferAction $action): RedirectResponse
    {
        $sourceLocation = InventoryLocation::findOrFail($request->input('source_location_id'));
        $this->authorize('dispatchTransfer', $sourceLocation);

        $validated = $request->validate([
            'source_location_id' => 'required|string',
            'destination_location_id' => 'required|string|different:source_location_id',
            'items' => 'required|array|min:1',
            'items.*.medication_id' => 'required|string',
            'items.*.batch_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $action->execute(
            $validated['source_location_id'],
            $validated['destination_location_id'],
            $validated['items'],
            auth()->id(),
            $validated['notes'] ?? null
        );

        return back()->with('success', 'Stock transfer dispatched in transit successfully.');
    }

    public function confirmTransfer(Request $request, string $id, ConfirmStockTransferAction $action): RedirectResponse
    {
        $transfer = StockTransfer::with('destinationLocation')->findOrFail($id);
        $this->authorize('confirmTransfer', $transfer->destinationLocation);

        $validated = $request->validate([
            'received_items' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $action->execute(
            $id,
            $validated['received_items'] ?? null,
            auth()->id(),
            $validated['notes'] ?? null
        );

        return back()->with('success', 'Stock transfer received and confirmed into warehouse inventory.');
    }
}
