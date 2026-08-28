<?php

namespace App\Domains\Pharmacy\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Pharmacy\Actions\ReceiveStockBatchAction;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class InventoryBatchController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['pharmacy.inventory.view']);

        // Same can-map keys/slugs as DispensingController::index() — both
        // render Domains/Pharmacy/PharmacyQueue.vue and must not drift.
        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'queue' => 'pharmacy.prescription.view',
            'formulary' => 'pharmacy.inventory.view',
            'movements' => 'pharmacy.inventory.view',
            'verify' => 'pharmacy.prescription.verify',
            'dispense' => 'pharmacy.dispense.execute',
            'storeBatch' => 'pharmacy.inventory.receive',
            'adjustBatch' => 'pharmacy.inventory.adjust',
        ]);

        $medications = MedicationFormulary::with([
            'itemMaster',
            'batches' => function ($q) {
                $q->orderBy('expiry_date', 'asc');
            },
            'stockMovements.performer',
            'stockMovements.batch',
        ])
            ->where('is_active', true)
            ->get();

        $recentMovements = $can['movements']
            ? StockMovement::with(['medication', 'batch', 'performer'])
                ->latest('created_at')
                ->take(500)
                ->get()
            : collect();

        $batches = InventoryBatch::with(['medication', 'facility'])
            ->orderBy('expiry_date', 'asc')
            ->get();

        return Inertia::render('Domains/Pharmacy/PharmacyQueue', [
            'can' => $can,
            'initialSection' => 'formulary',
            'medications' => $medications,
            'batches' => $batches,
            'recentMovements' => $recentMovements,
        ]);
    }

    public function store(Request $request, ReceiveStockBatchAction $action)
    {
        $this->authorize('receive', InventoryBatch::class);

        $validated = $request->validate([
            'medication_id' => 'required|string',
            'batch_number' => 'required|string|max:100',
            'expiry_date' => 'required|date|after:today',
            'manufacture_date' => 'nullable|date',
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0',
            'unit_selling_price' => 'nullable|numeric|min:0',
            'supplier_name' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $action->execute($validated);

        return back()->with('success', "Batch {$validated['batch_number']} received successfully into inventory.");
    }

    public function adjust(Request $request, InventoryBatch $batch)
    {
        $this->authorize('adjust', $batch);

        $validated = $request->validate([
            'new_quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $oldQty = $batch->current_quantity;
        $newQty = (int) $validated['new_quantity'];
        $diff = $newQty - $oldQty;

        if ($diff === 0) {
            return back()->with('info', 'Quantity unchanged.');
        }

        $batch->update([
            'current_quantity' => $newQty,
            'status' => $newQty === 0 ? 'Depleted' : 'Active',
        ]);

        StockMovement::create([
            'tenant_id' => $batch->tenant_id,
            'facility_id' => $batch->facility_id,
            'medication_id' => $batch->medication_id,
            'batch_id' => $batch->id,
            'movement_type' => $diff > 0 ? 'Adjustment_Positive' : 'Adjustment_Negative',
            'quantity_change' => $diff,
            'quantity_before' => $oldQty,
            'quantity_after' => $newQty,
            'reference_type' => 'ManualStockAdjustment',
            'reference_id' => $batch->id,
            'performed_by' => auth()->id(),
            'notes' => $validated['reason'],
        ]);

        return back()->with('success', "Batch {$batch->batch_number} stock adjusted to {$newQty} units.");
    }
}
