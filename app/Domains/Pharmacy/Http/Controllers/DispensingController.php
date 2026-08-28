<?php

namespace App\Domains\Pharmacy\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Pharmacy\Actions\DispenseDirectOtcAction;
use App\Domains\Pharmacy\Actions\DispenseMedicationAction;
use App\Domains\Pharmacy\Actions\VerifyPrescriptionAction;
use App\Domains\Pharmacy\Exceptions\PharmacyException;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Pharmacy\Models\StockMovement;
use App\Domains\Scheduling\Models\QueueTicket;
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
            'otc' => 'pharmacy.dispense.execute',
            'formulary' => 'pharmacy.inventory.view',
            'movements' => 'pharmacy.inventory.view',
            'verify' => 'pharmacy.prescription.verify',
            'dispense' => 'pharmacy.dispense.execute',
            'storeBatch' => 'pharmacy.inventory.receive',
            'adjustBatch' => 'pharmacy.inventory.adjust',
            'billing' => 'billing.invoice.view',
        ];
    }

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['pharmacy.prescription.view']);

        $can = $this->buildSectionCanMap($request->user(), $authService, $this->sectionSlugs());

        $prescriptions = $can['queue'] ? Prescription::with([
            'patient.allergies',
            'patient.policies.insuranceCompany',
            'prescriber',
            'medication.batches' => function ($q) {
                $q->where('status', 'Active')->orderBy('expiry_date', 'asc');
            },
            'dispenseEvents.dispenseEventBatches.batch',
            'encounter.invoices.lineItems',
        ])
            ->whereIn('status', ['Pending', 'Verified', 'Partially Dispensed', 'Dispensed'])
            ->orderBy('created_at', 'desc')
            ->get() : collect();

        $canSeeFormularyDetail = $can['formulary'] || $can['storeBatch'] || $can['adjustBatch'] || $can['otc'];

        $medications = $canSeeFormularyDetail
            ? MedicationFormulary::with([
                'itemMaster',
                'batches' => function ($q) {
                    $q->where('status', 'Active')->orderBy('expiry_date', 'asc');
                },
            ])
                ->where('is_active', true)
                ->pharmaceuticalOnly()
                ->get()
            : collect();

        $batches = $canSeeFormularyDetail
            ? InventoryBatch::with(['medication', 'facility'])
                ->orderBy('expiry_date', 'asc')
                ->get()
            : collect();

        $recentMovements = $can['movements']
            ? StockMovement::with(['medication', 'batch', 'performer'])
                ->latest('created_at')
                ->take(500)
                ->get()
            : collect();

        $waitingTickets = QueueTicket::where('current_service_point', 'Pharmacy')
            ->whereIn('status', ['Waiting', 'InProgress'])
            ->with(['patient.policies'])
            ->orderBy('joined_queue_at')
            ->get();

        return Inertia::render('Domains/Pharmacy/PharmacyQueue', [
            'can' => $can,
            'prescriptions' => $prescriptions,
            'medications' => $medications,
            'batches' => $batches,
            'recentMovements' => $recentMovements,
            'waitingTickets' => $waitingTickets,
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

        // Enforce Financial POS Clearance Gatekeeper:
        // Cash-paying patients must have settled all invoices before medications can leave the dispensary
        $prescription->load(['patient.policies', 'encounter.invoices']);
        $hasActiveInsurance = $prescription->patient?->policies?->contains(fn ($p) => in_array($p->status, ['Active', 'Verified']));

        if (! $hasActiveInsurance) {
            $unpaidInvoices = $prescription->encounter?->invoices?->filter(fn ($inv) => $inv->status !== 'Paid');
            if ($unpaidInvoices && $unpaidInvoices->isNotEmpty()) {
                $unpaidTotal = $unpaidInvoices->sum(fn ($inv) => (float) $inv->total_amount - (float) $inv->paid_amount);

                return back()->withErrors([
                    'dispense' => 'Cannot dispense medication: Patient has an unpaid balance of TZS '.number_format($unpaidTotal).' at the Cashier Desk. Payment settlement is required before dispensing.',
                ]);
            }
        }

        try {
            $action->execute($prescription, (int) $validated['quantity_dispensed'], $validated['pharmacist_notes'] ?? null);

            return back()->with('success', 'Medication dispensed successfully via FEFO allocation.');
        } catch (PharmacyException $e) {
            return back()->withErrors(['dispense' => $e->getMessage()]);
        }
    }

    public function dispenseDirectOtc(Request $request, DispenseDirectOtcAction $action)
    {
        $this->authorizeAnyWorkspacePermission($request->user(), app(AuthorizationService::class), ['pharmacy.dispense.execute']);

        $validated = $request->validate([
            'patient_id' => 'nullable|uuid|exists:patients,id',
            'ticket_id' => 'nullable|uuid|exists:queue_tickets,id',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.medication_id' => 'required|uuid|exists:medication_formularies,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.instructions' => 'nullable|string|max:255',
        ]);

        try {
            $result = $action->execute($validated);

            $msg = "Direct OTC sale completed successfully. {$result['total_amount']} TZS billed.";

            return back()->with('success', $msg);
        } catch (PharmacyException $e) {
            return back()->withErrors(['otc' => $e->getMessage()]);
        }
    }
}
