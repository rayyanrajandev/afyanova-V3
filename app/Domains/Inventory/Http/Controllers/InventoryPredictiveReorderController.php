<?php

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Actions\GeneratePredictiveReordersAction;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

/**
 * ADC-run-rate-based predictive purchase order generation. Split out of
 * InventoryWorkspaceController (see InventoryRequisitionController's
 * docblock for why).
 */
class InventoryPredictiveReorderController extends Controller
{
    use AuthorizesRequests;

    public function generatePredictiveReorder(GeneratePredictiveReordersAction $action): RedirectResponse
    {
        // No Tenant::first()/'default' fallback here: this endpoint sits
        // behind ['auth','verified'] middleware, so auth()->user() is
        // always present and users.tenant_id is NOT NULL at the DB level —
        // the fallback could never fire from a real request, and silently
        // defaulting to an arbitrary tenant/facility in a multi-tenant
        // system is a landmine, not a safety net (see the RLS/BYPASSRLS
        // hardening this session already did). Let a genuinely unexpected
        // null surface as an error instead of resolving to someone else's
        // data.
        $tenantId = auth()->user()?->tenant_id;
        $facilityId = auth()->user()?->facility_id ?? Facility::where('tenant_id', $tenantId)->first()?->id;

        $this->authorize('generatePredictiveReorder', [InventoryLocation::class, $facilityId]);

        $result = $action->execute($tenantId, $facilityId, true);

        $count = count($result['purchase_orders_created'] ?? []);
        if ($count > 0) {
            return back()->with('success', "{$count} replenishment Purchase Order(s) generated successfully based on ADC run-rate.");
        }

        return back()->with('info', 'All inventory SKUs are currently stocked above safety reorder points.');
    }
}
