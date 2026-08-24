<?php

namespace App\Domains\Radiology\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Radiology\Actions\AmendRadiologyReportAction;
use App\Domains\Radiology\Actions\OrderImagingAction;
use App\Domains\Radiology\Actions\SignRadiologyReportAction;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Radiology\Models\RadiologyReport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class RadiologyWorkspaceController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['radiology.order.view']);

        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'signReport' => 'radiology.report.sign',
            'amendReport' => 'radiology.report.amend',
        ]);

        $orders = RadiologyOrder::with(['patient', 'encounter', 'orderingProvider', 'studies', 'reports.radiologist'])
            ->latest('created_at')
            ->take(100)
            ->get();

        $pendingOrders = $orders->where('status', 'Pending')->values();
        $reportingOrders = $orders->whereIn('status', ['In Progress', 'Completed'])->values();
        $signedOrders = $orders->where('status', 'Reported')->values();

        $metrics = [
            'total_pending' => $pendingOrders->count(),
            'in_reporting' => $reportingOrders->count(),
            'reported_today' => $signedOrders->count(),
            'critical_count' => RadiologyReport::where('is_critical_finding', true)->count(),
        ];

        return Inertia::render('Workspace/RadiologyWorkspace', [
            'can' => $can,
            'orders' => $orders,
            'pendingOrders' => $pendingOrders,
            'reportingOrders' => $reportingOrders,
            'signedOrders' => $signedOrders,
            'metrics' => $metrics,
        ]);
    }

    public function order(Request $request, Encounter $encounter, OrderImagingAction $action)
    {
        $this->authorize('create', [RadiologyOrder::class, $encounter->facility_id]);

        $validated = $request->validate([
            'modality' => 'required|string|in:X-Ray,Ultrasound,CT Scan,MRI,Echo',
            'procedure_name' => 'required|string|max:255',
            'body_site' => 'nullable|string|max:255',
            'clinical_indication' => 'nullable|string',
            'priority' => 'nullable|string|in:Routine,Urgent,STAT',
        ]);

        try {
            $order = $action->execute($encounter, $validated);

            return back()->with('success', "Imaging order {$order->order_number} placed.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }
    }

    public function signReport(Request $request, RadiologyOrder $order, SignRadiologyReportAction $action)
    {
        $this->authorize('signReport', $order);

        $validated = $request->validate([
            'radiology_study_id' => 'nullable|uuid',
            'findings' => 'required|string',
            'impression' => 'required|string',
            'recommendations' => 'nullable|string',
            'is_critical_finding' => 'nullable|boolean',
        ]);

        $action->execute($order, $validated);

        return back()->with('success', 'Radiology report signed.');
    }

    public function amendReport(Request $request, RadiologyReport $report, AmendRadiologyReportAction $action)
    {
        $this->authorize('amendReport', $report->order);

        $validated = $request->validate([
            'findings' => 'nullable|string',
            'impression' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'is_critical_finding' => 'nullable|boolean',
            'amendment_reason' => 'required|string|min:10',
        ]);

        try {
            $action->execute($report, $validated);

            return back()->with('success', 'Amendment recorded.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amendment' => $e->getMessage()]);
        }
    }
}
