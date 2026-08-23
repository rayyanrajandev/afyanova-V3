<?php

namespace App\Domains\Procedure\Http\Controllers;

use App\Domains\Clinical\Models\Encounter;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Procedure\Actions\BookSurgicalCaseAction;
use App\Domains\Procedure\Actions\CompleteWhoChecklistAction;
use App\Domains\Procedure\Actions\CreateProcedureOrderAction;
use App\Domains\Procedure\Actions\RecordPacuTelemetryAction;
use App\Domains\Procedure\Actions\RecordProcedureExecutionAction;
use App\Domains\Procedure\Models\OperatingSuite;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Procedure\Models\ProcedureExecution;
use App\Domains\Procedure\Models\ProcedureOrder;
use App\Domains\Procedure\Models\SurgicalBooking;
use App\Domains\Procedure\Models\WhoSurgicalChecklist;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class ProcedureWorkspaceController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $procedureCatalogs = ProcedureCatalog::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $dressingQueue = ProcedureOrder::with(['catalog', 'patient', 'orderingProvider', 'encounter'])
            ->whereIn('status', ['Ordered', 'InProgress'])
            ->whereHas('catalog', function ($q) {
                $q->where('tier_level', 'Tier1_Minor');
            })
            ->orderByRaw("CASE WHEN priority = 'Emergency' THEN 1 WHEN priority = 'Urgent' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc')
            ->get();

        $surgicalBookings = SurgicalBooking::with(['order.catalog', 'order.patient', 'suite', 'leadSurgeon', 'anesthetist', 'scrubNurse', 'whoChecklist', 'pacuRecord'])
            ->orderBy('scheduled_start', 'desc')
            ->get();

        $operatingSuites = OperatingSuite::where('is_active', true)->get();

        $completedExecutions = ProcedureExecution::with(['order.catalog', 'order.patient', 'performedBy', 'consumables.medication'])
            ->orderBy('completed_at', 'desc')
            ->limit(50)
            ->get();

        $consumableProducts = InventoryBatch::with('medication')
            ->where('current_quantity', '>', 0)
            ->where('status', 'Active')
            ->get();

        $wards = Ward::where('is_active', true)->get();

        $encountersForProcedures = Encounter::with(['patient', 'provider'])
            ->whereIn('status', ['Arrived', 'InProgress'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $metrics = [
            'total_procedures_today' => ProcedureOrder::whereDate('created_at', today())->count(),
            'pending_dressing_queue' => $dressingQueue->count(),
            'in_theatre_surgeries' => $surgicalBookings->whereIn('status', ['Scheduled', 'InTheatre'])->count(),
            'pacu_recovery_bay' => $surgicalBookings->where('status', 'PACU')->count(),
            'emergency_procedures' => ProcedureOrder::whereIn('priority', ['Emergency', 'Urgent'])->whereIn('status', ['Ordered', 'InProgress'])->count(),
        ];

        return Inertia::render('Workspace/ProcedureWorkspace', [
            'procedureCatalogs' => $procedureCatalogs,
            'dressingQueue' => $dressingQueue,
            'surgicalBookings' => $surgicalBookings,
            'operatingSuites' => $operatingSuites,
            'completedExecutions' => $completedExecutions,
            'consumableProducts' => $consumableProducts,
            'wards' => $wards,
            'encountersForProcedures' => $encountersForProcedures,
            'metrics' => $metrics,
        ]);
    }

    public function orderProcedure(Request $request, CreateProcedureOrderAction $action)
    {
        $this->authorize('order', ProcedureOrder::class);

        $validated = $request->validate([
            'encounter_id' => 'required|string|exists:encounters,id',
            'procedure_catalog_id' => 'required|string|exists:procedure_catalogs,id',
            'priority' => 'nullable|string|in:Routine,Urgent,Emergency',
            'clinical_indication' => 'nullable|string|max:500',
        ]);

        try {
            $encounter = Encounter::findOrFail((string) $validated['encounter_id']);
            $action->execute(
                $encounter,
                $validated['procedure_catalog_id'],
                $validated['priority'] ?? 'Routine',
                $validated['clinical_indication'] ?? null
            );

            return back()->with('success', 'Procedure ordered and routed to treatment queue.');
        } catch (\Exception $e) {
            return back()->withErrors(['order_procedure' => $e->getMessage()]);
        }
    }

    public function executeProcedure(Request $request, ProcedureOrder $order, RecordProcedureExecutionAction $action)
    {
        $this->authorize('execute', $order);

        $validated = $request->validate([
            'execution_setting' => 'required|string|in:DressingRoom,MinorTheatre,MajorTheatre',
            'anesthesia_type' => 'nullable|string|in:None,Local,Spinal,General,Sedation',
            'wound_condition' => 'nullable|string|in:Clean,Contaminated,Purulent,Granulating,Epithelializing',
            'findings_and_technique' => 'required|string|max:2000',
            'post_procedure_instructions' => 'nullable|string|max:1000',
            'follow_up_date' => 'nullable|date',
            'consumables' => 'nullable|array',
            'consumables.*.item_name' => 'required|string',
            'consumables.*.batch_id' => 'nullable|string',
            'consumables.*.quantity_used' => 'required|numeric|min:0.1',
            'consumables.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $action->execute(
                $order,
                $validated,
                $validated['consumables'] ?? []
            );

            return back()->with('success', 'Procedure execution recorded and consumable stock deducted.');
        } catch (\Exception $e) {
            return back()->withErrors(['execute_procedure' => $e->getMessage()]);
        }
    }

    public function bookSurgery(Request $request, ProcedureOrder $order, BookSurgicalCaseAction $action)
    {
        $this->authorize('bookSurgery', $order);

        $validated = $request->validate([
            'operating_suite_id' => 'required|string|exists:operating_suites,id',
            'lead_surgeon_id' => 'nullable|string|exists:users,id',
            'anesthetist_id' => 'nullable|string|exists:users,id',
            'scrub_nurse_id' => 'nullable|string|exists:users,id',
            'scheduled_start' => 'required|date',
            'scheduled_end' => 'required|date|after:scheduled_start',
            'urgency' => 'nullable|string|in:Elective,Urgent,Emergency',
        ]);

        try {
            $action->execute($order, $validated['operating_suite_id'], $validated);

            return back()->with('success', 'Surgical suite booked and WHO safety checklist initialized.');
        } catch (\Exception $e) {
            return back()->withErrors(['book_surgery' => $e->getMessage()]);
        }
    }

    public function saveWhoChecklist(Request $request, WhoSurgicalChecklist $checklist, CompleteWhoChecklistAction $action)
    {
        $this->authorize('saveChecklist', $checklist);

        $validated = $request->validate([
            'stage' => 'required|string|in:sign_in,time_out,sign_out',
            'sponge_and_needle_count_correct' => 'nullable|boolean',
            'specimens_labeled_correctly' => 'nullable|boolean',
        ]);

        try {
            $action->execute($checklist, $validated['stage'], $validated);

            return back()->with('success', "WHO Surgical Safety Checklist {$validated['stage']} signed off.");
        } catch (\Exception $e) {
            return back()->withErrors(['who_checklist' => $e->getMessage()]);
        }
    }

    public function savePacuScore(Request $request, SurgicalBooking $booking, RecordPacuTelemetryAction $action)
    {
        $this->authorize('recordPacu', $booking);

        $validated = $request->validate([
            'consciousness_score' => 'required|integer|min:0|max:2',
            'activity_score' => 'required|integer|min:0|max:2',
            'respiration_score' => 'required|integer|min:0|max:2',
            'circulation_score' => 'required|integer|min:0|max:2',
            'oxygen_saturation_score' => 'required|integer|min:0|max:2',
            'destination_ward_id' => 'nullable|string|exists:wards,id',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $action->execute($booking, $validated, $validated['destination_ward_id'] ?? null, $validated['notes'] ?? null);

            return back()->with('success', 'PACU recovery Aldrete telemetry recorded.');
        } catch (\Exception $e) {
            return back()->withErrors(['pacu_score' => $e->getMessage()]);
        }
    }
}
