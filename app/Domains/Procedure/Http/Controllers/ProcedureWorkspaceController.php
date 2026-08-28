<?php

namespace App\Domains\Procedure\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Procedure\Actions\BookSurgicalCaseAction;
use App\Domains\Procedure\Actions\CompleteWhoChecklistAction;
use App\Domains\Procedure\Actions\CreateProcedureOrderAction;
use App\Domains\Procedure\Actions\EnsureProcedureOrdersForWaitingTicketsAction;
use App\Domains\Procedure\Actions\RecordPacuTelemetryAction;
use App\Domains\Procedure\Actions\RecordProcedureExecutionAction;
use App\Domains\Procedure\Models\OperatingSuite;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Procedure\Models\ProcedureExecution;
use App\Domains\Procedure\Models\ProcedureOrder;
use App\Domains\Procedure\Models\SurgicalBooking;
use App\Domains\Procedure\Models\WhoSurgicalChecklist;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class ProcedureWorkspaceController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService, EnsureProcedureOrdersForWaitingTicketsAction $ensureOrders)
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['procedure.order.view']);

        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'orderProcedure' => 'procedure.order.create',
            'executeProcedure' => 'procedure.order.execute',
            'bookSurgery' => 'procedure.theatre.book',
            'saveWhoChecklist' => 'procedure.theatre.checklist',
            'savePacuScore' => 'procedure.theatre.pacu',
            'manageCatalog' => 'procedure.catalog.manage',
        ]);

        if (! $can['executeProcedure'] && $authService->hasPermission($request->user(), 'procedure.execute.dressing')) {
            $can['executeProcedure'] = true;
        }

        // 'catalogue' (procedureCatalogs) stays ungated below — reference data, not patient data.
        $procedureCatalogs = ProcedureCatalog::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Ensure active direct walk-ins holding Procedure queue tickets have
        // an order in the nursing worklist — see
        // EnsureProcedureOrdersForWaitingTicketsAction's own docblock: this
        // still runs on every page load (a GET handler performing writes),
        // not yet moved to the point a ticket is actually routed here.
        $ensureOrders->execute($request->user()->facility_id ?? null);

        $baseOrderQuery = ProcedureOrder::with([
            'catalog',
            'patient.policies.insuranceCompany',
            'orderingProvider',
            'encounter.invoices.lineItems',
            'executions.performedBy',
            'executions.consumables.medication',
        ])
            ->whereIn('status', ['Ordered', 'InProgress', 'In_Progress'])
            ->orderByRaw("CASE WHEN priority = 'Emergency' THEN 1 WHEN priority = 'Urgent' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc');

        // 1. Injections & Medication Administration Desk (Chumba cha Sindano)
        $injectionQueue = (clone $baseOrderQuery)
            ->whereHas('catalog', function ($q) {
                $q->whereIn('category', ['Injection', 'Bedside'])->where('tier_level', 'Tier1_Minor');
            })
            ->get();

        // 2. Wound Care & Sterile Dressing Desk (Chumba cha Vidonda)
        $dressingQueue = (clone $baseOrderQuery)
            ->whereHas('catalog', function ($q) {
                $q->where('category', 'Dressing')->where('tier_level', 'Tier1_Minor');
            })
            ->get();

        // 3. Minor Surgical Procedures & Bedside Surgery Desk (Upasuaji Mdogo)
        $minorSurgeryQueue = (clone $baseOrderQuery)
            ->whereHas('catalog', function ($q) {
                $q->whereIn('category', ['MinorSurgery', 'OBGYN'])->where('tier_level', 'Tier1_Minor');
            })
            ->get();

        // 4. Tier 2 Operating Theatres & Surgeries (Major Theatre)
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
            'pending_injections' => $injectionQueue->count(),
            'pending_dressings' => $dressingQueue->count(),
            'pending_minor_surgeries' => $minorSurgeryQueue->count(),
            'in_theatre_surgeries' => $surgicalBookings->whereIn('status', ['Scheduled', 'InTheatre'])->count(),
            'pacu_recovery_bay' => $surgicalBookings->where('status', 'PACU')->count(),
            'emergency_procedures' => ProcedureOrder::whereIn('priority', ['Emergency', 'Urgent'])->whereIn('status', ['Ordered', 'InProgress', 'In_Progress'])->count(),
        ];

        return Inertia::render('Workspace/ProcedureWorkspace', [
            'can' => $can,
            'procedureCatalogs' => $procedureCatalogs,
            'injectionQueue' => $injectionQueue,
            'dressingQueue' => $dressingQueue,
            'minorSurgeryQueue' => $minorSurgeryQueue,
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
            'execution_setting' => 'required|string|in:DressingRoom,InjectionRoom,MinorTheatre,MajorTheatre,Ward',
            'anesthesia_type' => 'nullable|string|in:None,Local,Spinal,General,Sedation',
            'wound_condition' => 'nullable|string|in:Clean,Contaminated,Purulent,Granulating,Epithelializing',
            'findings_and_technique' => 'required|string|max:2000',
            'post_procedure_instructions' => 'nullable|string|max:1000',
            'follow_up_date' => 'nullable|date',
            'medication_source' => 'nullable|string|in:FacilityPharmacy,PatientSupplied',
            'treatment_plan_type' => 'nullable|string|in:Single,Multi',
            'total_doses' => 'nullable|integer|min:1',
            'current_dose_number' => 'nullable|integer|min:1',
            'is_course_completed' => 'nullable|boolean',
            'remaining_doses' => 'nullable|integer|min:0',
            'next_action' => 'nullable|string|in:discharge,pharmacy,doctor,cashier',
            'consumables' => 'nullable|array',
            'consumables.*.item_name' => 'required|string',
            'consumables.*.batch_id' => 'nullable|string',
            'consumables.*.quantity_used' => 'required|numeric|min:0.1',
            'consumables.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        // Enforce Financial POS Clearance Gatekeeper:
        // STAT / Emergency bypasses payment; cash patients must settle cashier invoice before routine procedure execution
        $order->load(['patient.policies', 'encounter.invoices']);
        $isEmergency = in_array($order->priority, ['Emergency', 'STAT']);
        $hasActiveInsurance = $order->patient?->policies?->contains(fn ($p) => in_array($p->status, ['Active', 'Verified']));

        if (! $isEmergency && ! $hasActiveInsurance) {
            $unpaidInvoices = $order->encounter?->invoices?->filter(fn ($inv) => $inv->status !== 'Paid');
            if ($unpaidInvoices && $unpaidInvoices->isNotEmpty()) {
                $unpaidTotal = $unpaidInvoices->sum(fn ($inv) => (float) $inv->total_amount - (float) $inv->paid_amount);

                if ($unpaidTotal > 0) {
                    return back()->withErrors([
                        'execute_procedure' => 'Cannot execute procedure: Patient has an unpaid balance of TZS '.number_format($unpaidTotal).' at the Cashier Desk. Payment settlement is required before clinical procedure execution.',
                    ]);
                }
            }
        }

        try {
            $action->execute(
                $order,
                $validated,
                $validated['consumables'] ?? []
            );

            // Handle Post-Procedure Queue & Care Pathway Routing
            $nextAction = $validated['next_action'] ?? 'discharge';
            $linkedTicket = QueueTicket::where('encounter_id', $order->encounter_id)
                ->orWhere('patient_id', $order->patient_id)
                ->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])
                ->first();

            if ($linkedTicket) {
                if ($nextAction === 'discharge' || ! empty($validated['is_course_completed'])) {
                    $linkedTicket->update([
                        'status' => QueueTicketStatus::Completed,
                        'completed_at' => now(),
                    ]);
                } elseif ($nextAction === 'pharmacy') {
                    $linkedTicket->update([
                        'current_service_point' => 'Pharmacy',
                        'status' => QueueTicketStatus::Waiting,
                        'joined_queue_at' => now(),
                        'called_at' => null,
                    ]);
                } elseif ($nextAction === 'doctor') {
                    $linkedTicket->update([
                        'current_service_point' => 'Doctor',
                        'status' => QueueTicketStatus::Waiting,
                        'joined_queue_at' => now(),
                        'called_at' => null,
                    ]);
                } elseif ($nextAction === 'cashier') {
                    $linkedTicket->update([
                        'current_service_point' => 'Cashier',
                        'status' => QueueTicketStatus::Waiting,
                        'joined_queue_at' => now(),
                        'called_at' => null,
                    ]);
                }
            }

            return back()->with('success', 'Procedure execution recorded, consumable stock deducted, and queue updated.');
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

    public function storeCatalogItem(Request $request, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'procedure.catalog.manage') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'procedure_code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'category' => 'required|string|max:100',
            'tier_level' => 'required|in:Tier1_Minor,Tier2_Intermediate,Tier3_Major,Tier4_Specialized',
            'default_duration_minutes' => 'required|integer|min:5|max:720',
            'standard_price' => 'required|numeric|min:0',
            'requires_consent' => 'nullable|boolean',
            'requires_anesthesia' => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = $request->user()->tenant_id;
        $validated['is_active'] = true;
        $validated['requires_consent'] = $validated['requires_consent'] ?? false;
        $validated['requires_anesthesia'] = $validated['requires_anesthesia'] ?? false;

        $catalog = ProcedureCatalog::create($validated);

        return back()->with('success', "Procedure catalog item {$catalog->name} added successfully.");
    }

    public function updateCatalogItem(Request $request, ProcedureCatalog $catalog, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'procedure.catalog.manage') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'category' => 'required|string|max:100',
            'tier_level' => 'required|in:Tier1_Minor,Tier2_Intermediate,Tier3_Major,Tier4_Specialized',
            'default_duration_minutes' => 'required|integer|min:5|max:720',
            'standard_price' => 'required|numeric|min:0',
            'requires_consent' => 'nullable|boolean',
            'requires_anesthesia' => 'nullable|boolean',
            'is_active' => 'required|boolean',
        ]);

        $catalog->update($validated);

        return back()->with('success', "Procedure {$catalog->name} updated successfully.");
    }
}
