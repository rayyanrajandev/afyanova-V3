<?php

namespace App\Domains\Radiology\Http\Controllers;

use App\Domains\Clinical\Models\Encounter;
use App\Domains\Radiology\Actions\AmendRadiologyReportAction;
use App\Domains\Radiology\Actions\OrderImagingAction;
use App\Domains\Radiology\Actions\SignRadiologyReportAction;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Radiology\Models\RadiologyReport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

/**
 * No index()/workspace listing page here on purpose: unlike the other
 * Workspace/*Controller siblings, there's no Vue page under
 * resources/js/Pages/Workspace yet to render one against. These three
 * actions don't need one — each responds with a redirect, not a page —
 * so the backend is fully reachable via these routes today; a workspace
 * listing UI is frontend scope to add alongside a Vue page later.
 */
class RadiologyWorkspaceController extends Controller
{
    use AuthorizesRequests;

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
