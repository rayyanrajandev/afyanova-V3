<?php

namespace App\Domains\Inventory\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Inventory\Actions\GeneratePredictiveReordersAction;
use App\Domains\Inventory\Models\DdaRegisterLog;
use App\Domains\Inventory\Models\DepartmentRequisition;
use App\Domains\Inventory\Models\GoodsReceiptNote;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MedicalGasCylinder;
use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\StocktakeSession;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Inventory\Models\UnitOfMeasure;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Models\Department;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inventory workspace: the read-heavy `index()` render (ten sections' worth
 * of stock/catalog/procurement data) plus the two endpoints
 * (`searchCatalog`, `storeItem`) that share this file's `SECTION_SLUGS`/
 * `ACTION_SLUGS`/`canSeeItemCatalog()` permission plumbing.
 *
 * The other thirteen write endpoints that used to live here — requisitions,
 * transfers, purchase orders, goods receipt, stocktake, DDA logging,
 * predictive reorder generation — moved to their own per-resource
 * controllers (InventoryRequisitionController and siblings) once this file
 * passed 600 lines covering ten unrelated concerns. Each of those methods
 * was already a thin authorize+validate+Action wrapper with no dependency
 * on this class's shared state, so the move only relocates controller
 * glue — no Action, Policy, or route name/URL changed.
 */
class InventoryWorkspaceController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    private const SECTION_SLUGS = [
        'stock' => 'inventory.stock.view',
        'catalog' => 'inventory.catalog.view',
        'requisition' => 'inventory.requisition.view',
        'transfer' => 'inventory.transfer.view',
        'po' => 'inventory.po.view',
        'predictive' => 'inventory.predictive.view',
        'grn' => 'inventory.grn.view',
        'dda' => 'inventory.dda.view',
        'gas' => 'inventory.gas.view',
        'stocktake' => 'inventory.stocktake.view',
    ];

    // View permission and write permission are separate slugs (e.g.
    // inventory.requisition.view vs .create) — a role can hold one without
    // the other, so button visibility needs its own map, not a reuse of
    // SECTION_SLUGS.
    private const ACTION_SLUGS = [
        'storeItem' => 'inventory.catalog.manage',
        'storeRequisition' => 'inventory.requisition.create',
        'approveRequisition' => 'inventory.requisition.approve',
        'issueRequisition' => 'inventory.requisition.issue',
        'confirmRequisition' => 'inventory.requisition.confirm',
        'storeTransfer' => 'inventory.transfer.dispatch',
        'confirmTransfer' => 'inventory.transfer.confirm',
        'storePurchaseOrder' => 'inventory.po.create',
        'approvePurchaseOrder' => 'inventory.po.approve',
        'storeGoodsReceipt' => 'inventory.grn.receive',
        'storeStocktake' => 'inventory.stocktake.approve',
        'storeDdaLog' => 'inventory.dda.record',
        'generatePredictiveReorder' => 'inventory.predictive.generate',
    ];

    /**
     * Whether the user can see full item-master detail (incl. cost/selling
     * price) — the union of every section whose UI actually reads
     * `itemMasters`/the catalog-search endpoint: the standalone Catalog
     * tab, plus the requisition/transfer/PO/GRN/DDA creation forms, each of
     * which embeds an `AfyaItemCombobox` (or, for DDA, a plain item
     * dropdown) that needs item name/code/pricing to populate. Confirmed by
     * reading every one of InventoryWorkspace.vue's `itemMasters` consumers
     * — nothing outside these six write/catalog actions touches it.
     */
    private function canSeeItemCatalog($user, AuthorizationService $authService): bool
    {
        $slugs = [
            self::SECTION_SLUGS['catalog'],
            self::ACTION_SLUGS['storeRequisition'],
            self::ACTION_SLUGS['storeTransfer'],
            self::ACTION_SLUGS['storePurchaseOrder'],
            self::ACTION_SLUGS['storeGoodsReceipt'],
            self::ACTION_SLUGS['storeDdaLog'],
        ];

        return collect($slugs)->contains(fn (string $slug) => $authService->hasPermission($user, $slug));
    }

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, array_values(self::SECTION_SLUGS));

        $can = array_merge(
            $this->buildSectionCanMap($request->user(), $authService, self::SECTION_SLUGS),
            $this->buildSectionCanMap($request->user(), $authService, self::ACTION_SLUGS)
        );

        // No Tenant::first() fallback: behind ['auth','verified'], so
        // auth()->user() is always present and users.tenant_id is NOT NULL
        // at the DB level — the fallback could never fire from a real
        // request, and defaulting to an arbitrary tenant in a multi-tenant
        // system is a landmine, not a safety net.
        $tenantId = auth()->user()?->tenant_id;

        // Shared reference/lookup data (facility list, supplier list, units
        // of measure) needed across multiple sections' forms — not gated
        // per-section. Item-master detail (unit_cost_price/unit_selling_price
        // included) is the exception: it's gated below, since it's the one
        // piece of "shared reference data" that's also directly displayed
        // (catalog table, AfyaItemCombobox search results).
        $locations = InventoryLocation::with('facility')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $selectedLocationId = $request->get('location_id', $locations->first()?->id);

        $itemMasters = $this->canSeeItemCatalog($request->user(), $authService)
            ? ItemMaster::with(['baseUom', 'purchasingUom', 'medication'])
                ->where('tenant_id', $tenantId)
                ->get()
            : collect();

        $suppliers = Supplier::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $medications = MedicationFormulary::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $facilities = Facility::where('tenant_id', $tenantId)->get();
        $departments = Department::where('facility_id', $facilities->first()?->id)->get();
        $unitsOfMeasure = UnitOfMeasure::where('tenant_id', $tenantId)->get();
        $batches = InventoryBatch::with('medication')->where('tenant_id', $tenantId)->where('status', 'Active')->get();

        $stockBalances = $can['stock']
            ? InventoryStockBalance::with(['medication', 'batch', 'location'])
                ->where('tenant_id', $tenantId)
                ->when($selectedLocationId, fn ($q) => $q->where('location_id', $selectedLocationId))
                ->get()
            : collect();

        $requisitions = $can['requisition']
            ? DepartmentRequisition::with(['department', 'sourceLocation', 'destinationLocation', 'items.item', 'requestedBy', 'approvedBy', 'dispatchedBy', 'receivedBy'])
                ->where('tenant_id', $tenantId)
                ->latest()
                ->limit(40)
                ->get()
            : collect();

        $transfers = $can['transfer']
            ? StockTransfer::with(['sourceLocation', 'destinationLocation', 'items.medication', 'items.batch', 'dispatchedBy', 'receivedBy'])
                ->where('tenant_id', $tenantId)
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        $purchaseOrders = $can['po']
            ? PurchaseOrder::with(['supplier', 'facility', 'destinationLocation', 'items.medication', 'orderedBy', 'approvedBy'])
                ->where('tenant_id', $tenantId)
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        $goodsReceiptNotes = $can['grn']
            ? GoodsReceiptNote::with(['supplier', 'facility', 'location', 'purchaseOrder', 'items.medication', 'items.batch', 'receivedBy'])
                ->where('tenant_id', $tenantId)
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        $stocktakeSessions = $can['stocktake']
            ? StocktakeSession::with(['facility', 'location', 'items.medication', 'items.batch', 'initiatedBy', 'approvedBy'])
                ->where('tenant_id', $tenantId)
                ->latest()
                ->limit(20)
                ->get()
            : collect();

        $ddaLogs = $can['dda']
            ? DdaRegisterLog::with(['item', 'batch', 'patient', 'prescriber', 'administeringNurse', 'witness'])
                ->where('tenant_id', $tenantId)
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        $gasCylinders = $can['gas']
            ? MedicalGasCylinder::with('currentLocation')
                ->where('tenant_id', $tenantId)
                ->get()
            : collect();

        // Metrics — each key only computed from a section the user can see,
        // so an aggregate number never leaks a count derived from rows the
        // user isn't allowed to view individually.
        $totalStockValue = $can['stock']
            ? $stockBalances->sum(function ($b) {
                $cost = $b->batch ? (float) $b->batch->unit_cost : 0;

                return $b->quantity_on_hand * $cost;
            })
            : null;

        $inTransitCount = null;
        if ($can['transfer'] || $can['requisition']) {
            $inTransitCount = ($can['transfer'] ? $transfers->where('status', 'Dispatched_In_Transit')->count() : 0)
                + ($can['requisition'] ? $requisitions->where('status', 'Dispatched_In_Transit')->count() : 0);
        }

        $pendingRequisitionsCount = $can['requisition'] ? $requisitions->where('status', 'Submitted')->count() : null;
        $pendingPoCount = $can['po'] ? $purchaseOrders->whereIn('status', ['Draft', 'Submitted'])->count() : null;
        $reorderAlertsCount = $can['stock'] ? $stockBalances->filter(fn ($b) => $b->quantity_on_hand <= $b->reorder_level)->count() : null;

        $predictiveData = null;
        if ($can['predictive']) {
            $predictiveData = (new GeneratePredictiveReordersAction)
                ->execute($tenantId, $facilities->first()?->id ?? 'default', false);
        }

        return Inertia::render('Workspace/InventoryWorkspace', [
            'can' => $can,
            'locations' => $locations,
            'selectedLocationId' => $selectedLocationId,
            'stockBalances' => $stockBalances,
            'itemMasters' => $itemMasters,
            'requisitions' => $requisitions,
            'transfers' => $transfers,
            'purchaseOrders' => $purchaseOrders,
            'goodsReceiptNotes' => $goodsReceiptNotes,
            'stocktakeSessions' => $stocktakeSessions,
            'ddaLogs' => $ddaLogs,
            'gasCylinders' => $gasCylinders,
            'suppliers' => $suppliers,
            'medications' => $medications,
            'facilities' => $facilities,
            'departments' => $departments,
            'unitsOfMeasure' => $unitsOfMeasure,
            'batches' => $batches,
            'predictiveProcurement' => $predictiveData,
            'metrics' => [
                'total_valuation_tzs' => $totalStockValue,
                'in_transit_transfers' => $inTransitCount,
                'pending_requisitions' => $pendingRequisitionsCount,
                'pending_purchase_orders' => $pendingPoCount,
                'reorder_alerts_count' => $reorderAlertsCount,
                'total_locations' => $locations->count(),
                'total_items_catalog' => $can['catalog'] ? $itemMasters->count() : null,
                'predictive_reorders_needed' => $can['predictive'] ? ($predictiveData['items_needing_reorder_count'] ?? 0) : null,
            ],
        ]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->authorize('manageCatalog', InventoryLocation::class);

        // No Tenant::first() fallback: behind ['auth','verified'], so
        // auth()->user() is always present and users.tenant_id is NOT NULL
        // at the DB level — the fallback could never fire from a real
        // request, and defaulting to an arbitrary tenant in a multi-tenant
        // system is a landmine, not a safety net.
        $tenantId = auth()->user()?->tenant_id;

        $validated = $request->validate([
            'item_code' => 'required|string|unique:item_masters,item_code',
            'name' => 'required|string',
            'generic_name' => 'nullable|string',
            'category' => 'required|string',
            'base_uom_id' => 'nullable|string',
            'purchasing_uom_id' => 'nullable|string',
            'conversion_ratio' => 'required|integer|min:1',
            'reorder_level' => 'required|integer|min:0',
            'unit_cost_price' => 'required|numeric|min:0',
            'unit_selling_price' => 'required|numeric|min:0',
            'is_billable' => 'required|boolean',
            'is_cold_chain' => 'nullable|boolean',
            'is_dda_narcotic' => 'nullable|boolean',
        ]);

        ItemMaster::create(array_merge($validated, ['tenant_id' => $tenantId]));

        return back()->with('success', 'New item added to hospital catalog.');
    }

    public function searchCatalog(Request $request, AuthorizationService $authService): JsonResponse
    {
        abort_unless($this->canSeeItemCatalog($request->user(), $authService), 403);

        // No Tenant::first() fallback: behind ['auth','verified'], so
        // auth()->user() is always present and users.tenant_id is NOT NULL
        // at the DB level — the fallback could never fire from a real
        // request, and defaulting to an arbitrary tenant in a multi-tenant
        // system is a landmine, not a safety net.
        $tenantId = auth()->user()?->tenant_id;
        $sku = trim($request->get('sku', ''));
        $query = trim($request->get('q', ''));
        $category = $request->get('category', 'ALL');
        $limit = min((int) $request->get('limit', 30), 100);

        $queryBuilder = ItemMaster::with(['baseUom', 'purchasingUom', 'medication'])
            ->where('tenant_id', $tenantId);

        if ($sku !== '') {
            $exactItem = (clone $queryBuilder)->where('item_code', $sku)->first();
            if ($exactItem) {
                return response()->json([
                    'data' => [$exactItem],
                    'count' => 1,
                    'exact_sku_match' => true,
                ]);
            }
        }

        $items = $queryBuilder
            ->when($category && $category !== 'ALL', fn ($q) => $q->where('category', $category))
            ->when($query !== '', function ($q) use ($query) {
                $term = "%{$query}%";
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('generic_name', 'like', $term)
                        ->orWhere('item_code', 'like', $term);
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $items,
            'count' => $items->count(),
            'exact_sku_match' => false,
        ]);
    }
}
