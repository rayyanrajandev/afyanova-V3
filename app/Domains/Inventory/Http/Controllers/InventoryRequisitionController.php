<?php

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Actions\ApproveDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\ConfirmDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\CreateDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\IssueDepartmentRequisitionAction;
use App\Domains\Inventory\Models\DepartmentRequisition;
use App\Domains\Inventory\Models\InventoryLocation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Department store requisition lifecycle (request -> approve -> issue ->
 * confirm). Split out of InventoryWorkspaceController, which had grown to
 * 637 lines covering ten unrelated inventory sections; requisitions were
 * one of thirteen already-thin write methods there (each already delegated
 * its logic to its own Action — this move relocates the controller glue,
 * not business logic). Route names/URLs are unchanged, so nothing on the
 * frontend needed to change.
 */
class InventoryRequisitionController extends Controller
{
    use AuthorizesRequests;

    public function storeRequisition(Request $request, CreateDepartmentRequisitionAction $action): RedirectResponse
    {
        $this->authorize('createRequisition', [InventoryLocation::class, $request->input('facility_id')]);

        $validated = $request->validate([
            'facility_id' => 'required|string',
            'department_id' => 'nullable|string',
            'source_location_id' => 'required|string',
            'destination_location_id' => 'required|string|different:source_location_id',
            'requisition_type' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|string',
            'items.*.quantity_requested' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $action->execute(
            $validated['facility_id'],
            $validated['department_id'] ?? null,
            $validated['source_location_id'],
            $validated['destination_location_id'],
            $validated['items'],
            $validated['requisition_type'],
            auth()->id(),
            $validated['notes'] ?? null
        );

        return back()->with('success', 'Department store requisition submitted successfully.');
    }

    public function approveRequisition(Request $request, string $id, ApproveDepartmentRequisitionAction $action): RedirectResponse
    {
        $requisition = DepartmentRequisition::findOrFail($id);
        $this->authorize('approveRequisition', [InventoryLocation::class, $requisition->facility_id]);

        $validated = $request->validate([
            'approved_quantities' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $action->execute($id, $validated['approved_quantities'] ?? null, auth()->id(), $validated['notes'] ?? null);

        return back()->with('success', 'Department requisition approved.');
    }

    public function issueRequisition(Request $request, string $id, IssueDepartmentRequisitionAction $action): RedirectResponse
    {
        $requisition = DepartmentRequisition::findOrFail($id);
        $this->authorize('issueRequisition', [InventoryLocation::class, $requisition->facility_id]);

        $validated = $request->validate([
            'allocations' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $action->execute($id, $validated['allocations'] ?? null, auth()->id(), $validated['notes'] ?? null);

        return back()->with('success', 'Department requisition dispatched in-transit.');
    }

    public function confirmRequisition(Request $request, string $id, ConfirmDepartmentRequisitionAction $action): RedirectResponse
    {
        $requisition = DepartmentRequisition::findOrFail($id);
        $this->authorize('confirmRequisition', [InventoryLocation::class, $requisition->facility_id]);

        $validated = $request->validate([
            'received_quantities' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $action->execute($id, $validated['received_quantities'] ?? null, auth()->id(), $validated['notes'] ?? null);

        return back()->with('success', 'Store requisition received and confirmed into ward cabinet.');
    }
}
