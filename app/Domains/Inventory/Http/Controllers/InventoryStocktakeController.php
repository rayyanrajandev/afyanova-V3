<?php

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Actions\ReconcileStocktakeSessionAction;
use App\Domains\Inventory\Actions\RecordBlindStocktakeCountAction;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\StocktakeSession;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Physical stocktake counting/reconciliation. Split out of
 * InventoryWorkspaceController (see InventoryRequisitionController's
 * docblock for why).
 */
class InventoryStocktakeController extends Controller
{
    use AuthorizesRequests;

    public function storeStocktake(Request $request, ReconcileStocktakeSessionAction $action): RedirectResponse
    {
        $session = StocktakeSession::findOrFail($request->input('session_id'));
        $this->authorize('reconcileStocktake', [InventoryLocation::class, $session->facility_id]);

        $validated = $request->validate([
            'session_id' => 'required|string',
            'counts' => 'required|array|min:1',
            'counts.*.medication_id' => 'required|string',
            'counts.*.batch_id' => 'nullable|string',
            'counts.*.physical_counted_quantity' => 'required|integer|min:0',
            'counts.*.variance_reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $action->execute(
            $validated['session_id'],
            $validated['counts'],
            auth()->id(),
            $validated['notes'] ?? null
        );

        return back()->with('success', 'Stocktake session reconciled and balancing entries posted.');
    }

    public function recordCount(Request $request, StocktakeSession $session, RecordBlindStocktakeCountAction $action): RedirectResponse
    {
        $this->authorize('reconcileStocktake', [InventoryLocation::class, $session->facility_id]);

        $validated = $request->validate([
            'counts' => 'required|array|min:1',
            'counts.*.medication_id' => 'required|string',
            'counts.*.batch_id' => 'nullable|string',
            'counts.*.physical_counted_quantity' => 'required|integer|min:0',
            'counts.*.variance_reason' => 'nullable|string|max:255',
        ]);

        try {
            $action->execute($session, $validated['counts'], auth()->id());

            return back()->with('success', "Blind stocktake physical counts recorded for audit session {$session->session_number}.");
        } catch (Throwable $e) {
            return back()->withErrors(['stocktake_counts' => $e->getMessage()]);
        }
    }
}
