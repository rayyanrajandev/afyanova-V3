<?php

namespace App\Domains\Inventory\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Inventory\Actions\ApproveDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\ApprovePurchaseOrderAction;
use App\Domains\Inventory\Actions\ConfirmDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\ConfirmStockTransferAction;
use App\Domains\Inventory\Actions\CreateDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\CreatePurchaseOrderAction;
use App\Domains\Inventory\Actions\CreateStockTransferAction;
use App\Domains\Inventory\Actions\GeneratePredictiveReordersAction;
use App\Domains\Inventory\Actions\IssueDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\ProcessGoodsReceiptAction;
use App\Domains\Inventory\Actions\ReconcileStocktakeSessionAction;
use App\Domains\Inventory\Actions\RecordDdaAdministrationAction;
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
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

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

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, array_values(self::SECTION_SLUGS));

        $can = array_merge(
            $this->buildSectionCanMap($request->user(), $authService, self::SECTION_SLUGS),
            $this->buildSectionCanMap($request->user(), $authService, self::ACTION_SLUGS)
        );

        $tenantId = auth()->user()?->tenant_id ?? Tenant::first()?->id;

        // Shared reference/lookup data (facility list, item names, supplier
        // list, units of measure) needed across multiple sections' forms —
        // not gated per-section, since e.g. the requisition-creation form
        // needs the item picker regardless of whether the user can also see
        // the standalone catalog tab.
        $locations = InventoryLocation::with('facility')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $selectedLocationId = $request->get('location_id', $locations->first()?->id);

        $itemMasters = ItemMaster::with(['baseUom', 'purchasingUom', 'medication'])
            ->where('tenant_id', $tenantId)
            ->get();

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

        $tenantId = auth()->user()?->tenant_id ?? Tenant::first()?->id;

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

    public function storeDdaLog(Request $request, RecordDdaAdministrationAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'facility_id' => 'required|string',
            'item_id' => 'required|string',
            'batch_id' => 'required|string',
            'encounter_id' => 'nullable|string',
            'patient_id' => 'nullable|string',
            'prescriber_id' => 'nullable|string',
            'witness_user_id' => 'nullable|string',
            'dose_administered' => 'required|numeric|min:0.01',
            'dose_wasted_discarded' => 'nullable|numeric|min:0',
            'indication' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $this->authorize('recordDda', [InventoryLocation::class, $validated['facility_id']]);

        $action->execute(
            $validated['facility_id'],
            $validated['item_id'],
            $validated['batch_id'],
            (float) $validated['dose_administered'],
            (float) ($validated['dose_wasted_discarded'] ?? 0),
            $validated['encounter_id'] ?? null,
            $validated['patient_id'] ?? null,
            $validated['prescriber_id'] ?? null,
            auth()->id(),
            $validated['witness_user_id'] ?? null,
            $validated['indication'],
            $validated['notes'] ?? null
        );

        return back()->with('success', 'DDA Controlled Substance administration logged successfully.');
    }

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

    public function searchCatalog(Request $request): JsonResponse
    {
        $tenantId = auth()->user()?->tenant_id ?? Tenant::first()?->id;
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

    public function generatePredictiveReorder(Request $request, GeneratePredictiveReordersAction $action): RedirectResponse
    {
        $tenantId = auth()->user()?->tenant_id ?? Tenant::first()?->id;
        $facilityId = auth()->user()?->facility_id ?? Facility::where('tenant_id', $tenantId)->first()?->id ?? 'default';

        $this->authorize('generatePredictiveReorder', [InventoryLocation::class, $facilityId]);

        $result = $action->execute($tenantId, $facilityId, true);

        $count = count($result['purchase_orders_created'] ?? []);
        if ($count > 0) {
            return back()->with('success', "{$count} replenishment Purchase Order(s) generated successfully based on ADC run-rate.");
        }

        return back()->with('info', 'All inventory SKUs are currently stocked above safety reorder points.');
    }
}
