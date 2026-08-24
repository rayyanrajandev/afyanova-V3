<?php

namespace App\Domains\Pharmacy\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Pharmacy\Actions\DispenseMedicationAction;
use App\Domains\Pharmacy\Actions\VerifyPrescriptionAction;
use App\Domains\Pharmacy\Exceptions\PharmacyException;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DispensingController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    /**
     * Both this route and InventoryBatchController::index() render the same
     * Domains/Pharmacy/PharmacyQueue.vue component; the can map's keys must
     * stay identical across both controllers (built via the shared trait
     * with the same slugs) so a user can't get looser section access by
     * switching tabs after entering through whichever route had the wider
     * page-level bar.
     */
    private function sectionSlugs(): array
    {
        return [
            'queue' => 'pharmacy.prescription.view',
            'formulary' => 'pharmacy.inventory.view',
            'movements' => 'pharmacy.inventory.view',
            'verify' => 'pharmacy.prescription.verify',
            'dispense' => 'pharmacy.dispense.execute',
            'storeBatch' => 'pharmacy.inventory.receive',
            'adjustBatch' => 'pharmacy.inventory.adjust',
        ];
    }

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['pharmacy.prescription.view']);

        $can = $this->buildSectionCanMap($request->user(), $authService, $this->sectionSlugs());

        $prescriptions = $can['queue'] ? Prescription::with([
            'patient.allergies',
            'prescriber',
            'medication.batches' => function ($q) {
                $q->where('status', 'Active')->orderBy('expiry_date', 'asc');
            },
            'dispenseEvents.dispenseEventBatches.batch',
            'encounter.invoices',
        ])
            ->whereIn('status', ['Pending', 'Verified', 'Partially Dispensed', 'Dispensed'])
            ->orderBy('created_at', 'desc')
            ->get() : collect();

        // Shared reference data for the dispensing action itself (drug/batch
        // lookup) as well as the formulary tab display — kept loaded
        // whenever the page loads at all rather than split per-tab, since
        // gating it further would break the dispense flow with no real
        // data-sensitivity benefit (formulary/batch data isn't patient PII).
        $medications = MedicationFormulary::with([
            'batches' => function ($q) {
                $q->orderBy('expiry_date', 'asc');
            },
            'stockMovements' => function ($q) {
                $q->latest('created_at')->take(10);
            },
        ])
            ->where('is_active', true)
            ->get();

        $batches = InventoryBatch::with(['medication', 'facility'])
            ->orderBy('expiry_date', 'asc')
            ->get();

        $recentMovements = $can['movements']
            ? StockMovement::with(['medication', 'batch', 'performer'])
                ->latest('created_at')
                ->take(50)
                ->get()
            : collect();

        return Inertia::render('Domains/Pharmacy/PharmacyQueue', [
            'can' => $can,
            'prescriptions' => $prescriptions,
            'medications' => $medications,
            'batches' => $batches,
            'recentMovements' => $recentMovements,
            'initialSection' => 'queue',
        ]);
    }

    public function verify(Request $request, Prescription $prescription, VerifyPrescriptionAction $action)
    {
        $this->authorize('verify', $prescription);

        $validated = $request->validate([
            'approve' => 'required|boolean',
            'reason' => 'nullable|string',
        ]);

        $action->execute($prescription, (bool) $validated['approve'], $validated['reason'] ?? null);

        $status = $validated['approve'] ? 'verified' : 'rejected';

        return back()->with('success', "Prescription {$status}.");
    }

    public function dispense(Request $request, Prescription $prescription, DispenseMedicationAction $action)
    {
        $this->authorize('dispense', $prescription);

        $validated = $request->validate([
            'quantity_dispensed' => 'required|integer|min:1',
            'pharmacist_notes' => 'nullable|string',
        ]);

        try {
            $action->execute($prescription, (int) $validated['quantity_dispensed'], $validated['pharmacist_notes'] ?? null);

            return back()->with('success', 'Medication dispensed successfully via FEFO allocation.');
        } catch (PharmacyException $e) {
            return back()->withErrors(['dispense' => $e->getMessage()]);
        }
    }
}
