<?php

namespace App\Domains\Laboratory\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Laboratory\Actions\AmendLabResultAction;
use App\Domains\Laboratory\Actions\CollectSpecimenAction;
use App\Domains\Laboratory\Actions\CreateCustomLabTestAction;
use App\Domains\Laboratory\Actions\RecordLabResultsAction;
use App\Domains\Laboratory\Actions\VerifyLabResultsAction;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class LaboratoryWorkspaceController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService)
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['lab.order.view']);

        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'collectSample' => 'lab.specimen.collect',
            'saveResults' => 'lab.result.record',
            'verifyResults' => 'lab.result.verify',
            'amendResults' => 'lab.result.verify',
            'storeTest' => 'lab.catalog.manage',
            'clinical' => 'clinical.encounter.view',
            'billing' => 'billing.invoice.view',
            'pharmacy' => 'pharmacy.prescription.view',
            'queue' => 'scheduling.queue.view',
            'procedure' => 'procedure.order.view',
            'radiology' => 'radiology.order.view',
        ]);

        // 'catalogue' (labTests) stays ungated below — reference data, not patient data.
        $labTests = LabTest::with('inventoryItem')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $labConsumables = ItemMaster::where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('category', ['Medical Supplies', 'Laboratory Consumables', 'Consumables'])
                    ->orWhere('name', 'like', '%Tube%')
                    ->orWhere('name', 'like', '%Container%')
                    ->orWhere('name', 'like', '%Vacutainer%')
                    ->orWhere('name', 'like', '%Swab%')
                    ->orWhere('name', 'like', '%Bottle%')
                    ->orWhere('name', 'like', '%Cup%');
            })
            ->select(['id', 'item_code', 'name', 'category'])
            ->orderBy('name')
            ->get();

        $pendingSamples = LabOrderItem::with([
            'labTest.inventoryItem',
            'labOrder.patient.policies.insuranceCompany',
            'labOrder.orderingProvider',
            'labOrder.encounter.invoices',
        ])
            ->whereIn('status', ['Pending', 'Ordered'])
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->get();

        $testingWorklist = LabOrderItem::with([
            'labTest.inventoryItem',
            'labOrder.patient.policies.insuranceCompany',
            'labOrder.orderingProvider',
            'labOrder.encounter.invoices',
            'performedBy',
        ])
            ->whereIn('status', ['Sample Collected', 'Testing', 'In Progress'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $completedResults = LabOrderItem::with([
            'labTest.inventoryItem',
            'labOrder.patient.policies.insuranceCompany',
            'labOrder.orderingProvider',
            'labOrder.encounter.invoices',
            'performedBy',
            'verifiedBy',
        ])
            ->where('status', 'Completed')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        $metrics = [
            'total_orders' => LabOrder::count(),
            'pending_phlebotomy' => $pendingSamples->count(),
            'in_testing' => $testingWorklist->count(),
            'completed_today' => LabOrderItem::where('status', 'Completed')->whereDate('updated_at', today())->count(),
            'critical_alerts' => LabOrderItem::where('has_critical_value', true)->count(),
        ];

        return Inertia::render('Workspace/LaboratoryWorkspace', [
            'can' => $can,
            'labTests' => $labTests,
            'labConsumables' => $labConsumables,
            'pendingSamples' => $pendingSamples,
            'testingWorklist' => $testingWorklist,
            'completedResults' => $completedResults,
            'metrics' => $metrics,
        ]);
    }

    public function collectSample(Request $request, LabOrderItem $item, CollectSpecimenAction $action)
    {
        $this->authorize('collect', $item);

        $validated = $request->validate([
            'specimen_barcode' => 'nullable|string|max:50',
            'technician_remarks' => 'nullable|string|max:500',
        ]);

        // Enforce Financial POS Clearance Gatekeeper:
        // STAT/Emergency bypasses payment; cash patients must settle cashier invoice before routine specimen collection
        $item->load(['labOrder.patient.policies', 'labOrder.encounter.invoices']);
        $isStat = $item->labOrder?->priority === 'STAT';
        $hasActiveInsurance = $item->labOrder?->patient?->policies?->contains(fn ($p) => in_array($p->status, ['Active', 'Verified']));

        if (! $isStat && ! $hasActiveInsurance) {
            $unpaidInvoices = $item->labOrder?->encounter?->invoices?->filter(fn ($inv) => $inv->status !== 'Paid');
            if ($unpaidInvoices && $unpaidInvoices->isNotEmpty()) {
                $unpaidTotal = $unpaidInvoices->sum(fn ($inv) => (float) $inv->total_amount - (float) $inv->paid_amount);

                return back()->withErrors([
                    'collect_sample' => 'Cannot collect laboratory specimen: Patient has an unpaid balance of TZS '.number_format($unpaidTotal).' at the Cashier Desk. Payment is required before routine specimen collection.',
                ]);
            }
        }

        try {
            $action->execute(
                $item,
                $validated['specimen_barcode'] ?? null,
                $validated['technician_remarks'] ?? null
            );

            return back()->with('success', 'Specimen collected, inventory tube deducted, and accession barcode generated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['collect_sample' => $e->getMessage()]);
        }
    }

    public function saveResults(Request $request, LabOrderItem $item, RecordLabResultsAction $action)
    {
        $this->authorize('recordResults', $item);

        $validated = $request->validate([
            'results' => 'required|array',
            'technician_remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $updatedItem = $action->execute(
                $item,
                $validated['results'],
                $validated['technician_remarks'] ?? null
            );

            $msg = $updatedItem->has_critical_value
                ? 'CRITICAL PANIC VALUE RECORDED! Attending physician alert flagged.'
                : 'Diagnostic test findings recorded successfully.';

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['save_results' => $e->getMessage()]);
        }
    }

    public function verifyResults(Request $request, LabOrderItem $item, VerifyLabResultsAction $action)
    {
        $this->authorize('verify', $item);

        $validated = $request->validate([
            'pathologist_notes' => 'nullable|string|max:500',
        ]);

        try {
            $action->execute($item, $validated['pathologist_notes'] ?? null);

            // Check if all items in this LabOrder are now verified
            $labOrder = $item->labOrder;
            $allCompleted = $labOrder && $labOrder->items()->where('status', '!=', 'Completed')->count() === 0;

            if ($allCompleted) {
                $labOrder->update([
                    'status' => 'Completed',
                    'completed_at' => now(),
                ]);

                // Post-Lab Care Pathway: If doctor ordered, route back to Doctor Consultation Desk
                $linkedTicket = QueueTicket::where('encounter_id', $labOrder->encounter_id)
                    ->orWhere('patient_id', $labOrder->patient_id)
                    ->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])
                    ->first();

                if ($linkedTicket) {
                    $isDoctorEncounter = $labOrder->encounter?->encounter_type === 'OPD'
                        || $labOrder->ordering_provider_id !== null;

                    if ($isDoctorEncounter) {
                        $linkedTicket->update([
                            'current_service_point' => 'Doctor',
                            'status' => QueueTicketStatus::Waiting,
                            'joined_queue_at' => now(),
                            'called_at' => null,
                        ]);
                    } else {
                        // Direct Lab walk-in finishes after report verification
                        $linkedTicket->update([
                            'status' => 'Completed',
                            'completed_at' => now(),
                        ]);
                    }
                }
            }

            return back()->with('success', 'Investigation results electronically verified and locked by pathologist. Patient routed for next clinical step.');
        } catch (\Exception $e) {
            return back()->withErrors(['verify_results' => $e->getMessage()]);
        }
    }

    public function storeTest(Request $request, CreateCustomLabTestAction $action, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'lab.catalog.manage'), 403);

        $validated = $request->validate([
            'test_code' => 'required|string|max:20',
            'name' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'specimen_type' => 'required|string|max:100',
            'inventory_item_id' => 'nullable|string|max:50',
            'turnaround_time_minutes' => 'required|integer|min:5|max:1440',
            'price' => 'required|numeric|min:0',
            'parameters' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $action->execute($validated);

            $msg = ! empty($validated['is_active'])
                ? 'Diagnostic test profile created and activated for clinical ordering.'
                : 'Diagnostic test profile saved as Draft (Pending Approval).';

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['store_test' => $e->getMessage()]);
        }
    }

    public function updateTest(Request $request, LabTest $test, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'lab.catalog.manage'), 403);

        $validated = $request->validate([
            'test_code' => 'required|string|max:20',
            'name' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'specimen_type' => 'required|string|max:100',
            'inventory_item_id' => 'nullable|string|max:50',
            'turnaround_time_minutes' => 'required|integer|min:5|max:1440',
            'price' => 'required|numeric|min:0',
            'parameters' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $test->update($validated);

            return back()->with('success', "Diagnostic test profile [{$test->test_code}] updated successfully.");
        } catch (\Exception $e) {
            return back()->withErrors(['update_test' => $e->getMessage()]);
        }
    }

    public function toggleTestStatus(Request $request, LabTest $test, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'lab.catalog.manage'), 403);

        try {
            $test->update([
                'is_active' => ! $test->is_active,
            ]);

            $statusText = $test->is_active
                ? 'approved and activated for doctor ordering'
                : 'deactivated (hidden from doctor ordering)';

            return back()->with('success', "Diagnostic test profile [{$test->test_code}] {$statusText}.");
        } catch (\Exception $e) {
            return back()->withErrors(['toggle_status' => $e->getMessage()]);
        }
    }

    public function amendResults(Request $request, LabOrderItem $item, AmendLabResultAction $action, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'lab.result.verify') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'results' => 'required|array',
            'amendment_reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $amendedItem = $action->execute($item, $validated['results'], $validated['amendment_reason']);

            return back()->with('success', "Lab test result amended successfully with immutable forensic audit trail (Amended Item #{$amendedItem->id}).");
        } catch (\Throwable $e) {
            return back()->withErrors(['amend_results' => $e->getMessage()]);
        }
    }
}
