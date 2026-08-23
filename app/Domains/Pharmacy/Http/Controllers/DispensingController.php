<?php

namespace App\Domains\Pharmacy\Http\Controllers;

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
    use AuthorizesRequests;

    public function index(): Response
    {
        $prescriptions = Prescription::with([
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
            ->get();

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

        $recentMovements = StockMovement::with(['medication', 'batch', 'performer'])
            ->latest('created_at')
            ->take(50)
            ->get();

        return Inertia::render('Domains/Pharmacy/PharmacyQueue', [
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
