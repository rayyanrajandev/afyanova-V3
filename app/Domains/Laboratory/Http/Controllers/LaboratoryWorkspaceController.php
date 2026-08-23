<?php

namespace App\Domains\Laboratory\Http\Controllers;

use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Laboratory\Actions\CollectSpecimenAction;
use App\Domains\Laboratory\Actions\CreateCustomLabTestAction;
use App\Domains\Laboratory\Actions\RecordLabResultsAction;
use App\Domains\Laboratory\Actions\VerifyLabResultsAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class LaboratoryWorkspaceController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $labTests = LabTest::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $pendingSamples = LabOrderItem::with(['labTest', 'labOrder.patient', 'labOrder.orderingProvider', 'labOrder.encounter'])
            ->whereIn('status', ['Pending', 'Ordered'])
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->get();

        $testingWorklist = LabOrderItem::with(['labTest', 'labOrder.patient', 'labOrder.orderingProvider', 'labOrder.encounter', 'performedBy'])
            ->whereIn('status', ['Sample Collected', 'Testing', 'In Progress'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $completedResults = LabOrderItem::with(['labTest', 'labOrder.patient', 'labOrder.orderingProvider', 'labOrder.encounter', 'performedBy', 'verifiedBy'])
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
            'labTests' => $labTests,
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

        try {
            $action->execute(
                $item,
                $validated['specimen_barcode'] ?? null,
                $validated['technician_remarks'] ?? null
            );

            return back()->with('success', 'Specimen collected and accession barcode generated successfully.');
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

            return back()->with('success', 'Investigation results electronically verified and locked by pathologist.');
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
            'specimen_type' => 'required|string|max:50',
            'turnaround_time_minutes' => 'required|integer|min:5|max:1440',
            'price' => 'required|numeric|min:0',
            'parameters' => 'nullable|array',
        ]);

        try {
            $action->execute($validated);

            return back()->with('success', 'Diagnostic test profile added to master catalog.');
        } catch (\Exception $e) {
            return back()->withErrors(['store_test' => $e->getMessage()]);
        }
    }
}
