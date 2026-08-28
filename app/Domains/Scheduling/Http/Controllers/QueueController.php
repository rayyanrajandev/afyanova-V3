<?php

namespace App\Domains\Scheduling\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Patient\Models\Patient;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Scheduling\Actions\CheckInPatientDirectlyAction;
use App\Domains\Scheduling\Actions\TransferQueueAction;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['scheduling.queue.view']);

        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'call' => 'scheduling.queue.call',
            'transfer' => 'scheduling.queue.transfer',
            'clinical' => 'clinical.encounter.view',
            'billing' => 'billing.invoice.view',
            'checkInDirect' => 'scheduling.appointment.checkin',
            'canCallDoctor' => 'clinical.diagnosis.manage',
            'canCallTriage' => 'clinical.vitals.record',
            'canCallProcedure' => 'procedure.execute.dressing',
            'canCallLab' => 'lab.specimen.collect',
            'canCallPharmacy' => 'pharmacy.dispense.execute',
            'canCallCashier' => 'billing.payment.collect',
            'isReceptionist' => 'scheduling.appointment.checkin',
        ]);

        if (! $can['canCallProcedure'] && $authService->hasPermission($request->user(), 'procedure.order.execute')) {
            $can['canCallProcedure'] = true;
        }

        // Calculate live patient counts for each active hospital service point
        $pointCounts = QueueTicket::whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])
            ->selectRaw('current_service_point, count(*) as count')
            ->groupBy('current_service_point')
            ->pluck('count', 'current_service_point')
            ->toArray();

        $pointCounts['All'] = array_sum($pointCounts);

        $servicePoint = $request->query('point', 'All');

        $query = QueueTicket::with([
            'patient.policies.insuranceCompany',
            'encounter.invoices.lineItems',
            'encounter.labOrders.items.labTest',
            'encounter.procedureOrders.catalog',
            'encounter.procedureOrders.executions',
        ])
            ->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])
            ->orderByRaw("CASE priority WHEN 'Emergency' THEN 1 WHEN 'Urgent' THEN 2 ELSE 3 END")
            ->orderBy('joined_queue_at');

        if ($servicePoint && $servicePoint !== 'All') {
            $query->where('current_service_point', $servicePoint);
        }

        $tickets = $query->get();

        $patients = Patient::where('status', 'Active')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get(['id', 'primary_mrn', 'first_name', 'last_name', 'gender', 'dob', 'status']);

        $labTests = LabTest::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'test_code', 'name', 'price', 'category', 'specimen_type']);

        $procedureCatalogs = ProcedureCatalog::where('is_active', true)
            ->where('tier_level', 'Tier1_Minor')
            ->orderBy('name')
            ->get(['id', 'procedure_code', 'name', 'category', 'tier_level', 'standard_price']);

        return Inertia::render('Domains/Scheduling/LiveQueue', [
            'can' => $can,
            'tickets' => $tickets,
            'patients' => $patients,
            'labTests' => $labTests,
            'procedureCatalogs' => $procedureCatalogs,
            'currentPoint' => $servicePoint,
            'pointCounts' => $pointCounts,
        ]);
    }

    public function checkInDirect(Request $request, AuthorizationService $authService, CheckInPatientDirectlyAction $action)
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['scheduling.appointment.checkin']);

        $validated = $request->validate([
            'patient_id' => 'required|uuid|exists:patients,id',
            'service_point' => 'required|string|in:Triage,Doctor,Procedure,Lab,Pharmacy,Cashier',
            'visit_type' => 'required|string',
            'priority' => 'nullable|string|in:Routine,Urgent,Emergency',
            'reason' => 'nullable|string|max:500',
            'selected_lab_test_ids' => 'nullable|array',
            'selected_lab_test_ids.*' => 'string|exists:lab_tests,id',
            'procedure_catalog_id' => 'nullable|string|exists:procedure_catalogs,id',
            'payment_mode' => 'nullable|string|in:Cash,Insurance,Prepaid',
            'medication_source' => 'nullable|string|in:PatientSupplied,ClinicStock',
        ]);

        $ticket = $action->execute($validated, $request->user());

        return back()->with('success', "Patient checked in directly with ticket {$ticket->ticket_number} to {$validated['service_point']} desk.");
    }

    public function transfer(Request $request, QueueTicket $ticket, TransferQueueAction $action)
    {
        $this->authorize('transfer', $ticket);

        $validated = $request->validate([
            'next_service_point' => 'required|string|in:Triage,Doctor,Procedure,Lab,Pharmacy,Cashier',
            'reason' => 'nullable|string|max:500',
        ]);

        $oldPoint = $ticket->current_service_point;
        $action->execute($ticket, $validated['next_service_point'], $validated['reason'] ?? null);

        $patientName = trim(($ticket->patient?->first_name ?? '').' '.($ticket->patient?->last_name ?? ''));

        return back()->with('success', "Patient {$patientName} ({$ticket->ticket_number}) re-routed from {$oldPoint} to {$validated['next_service_point']}.");
    }

    public function call(Request $request, QueueTicket $ticket, AuthorizationService $authService)
    {
        $this->authorize('call', $ticket);

        // Enforce Financial POS Clearance Gatekeeper for Direct Service Desks:
        // Cash-paying routine patients must settle cashier invoice before staff can call them into Procedure, Lab, or Pharmacy
        if (in_array($ticket->current_service_point, ['Procedure', 'Lab', 'Pharmacy'])) {
            $ticket->load(['patient.policies', 'encounter.invoices']);
            $isEmergency = in_array($ticket->priority, ['Emergency', 'STAT']);
            $hasActiveInsurance = $ticket->patient?->policies?->contains(fn ($p) => in_array($p->status, ['Active', 'Verified']));

            if (! $isEmergency && ! $hasActiveInsurance) {
                $unpaidInvoices = $ticket->encounter?->invoices?->filter(fn ($inv) => $inv->status !== 'Paid');
                if ($unpaidInvoices && $unpaidInvoices->isNotEmpty()) {
                    $unpaidTotal = $unpaidInvoices->sum(fn ($inv) => (float) $inv->total_amount - (float) $inv->paid_amount);
                    if ($unpaidTotal > 0) {
                        return back()->withErrors([
                            'call' => "Cannot call patient to {$ticket->current_service_point} desk: Patient has an unpaid balance of TZS ".number_format($unpaidTotal).' at the Cashier Desk. Payment settlement is required before calling.',
                        ]);
                    }
                }
            }
        }

        $ticket->update([
            'status' => QueueTicketStatus::InProgress,
            'called_at' => now(),
        ]);

        $user = $request->user();

        // 1. Doctor Desk -> Route to clinical encounter charting
        if ($ticket->current_service_point === 'Doctor' && $authService->hasPermission($user, 'clinical.encounter.view')) {
            if ($ticket->encounter_id) {
                return redirect()->route('encounters.workspace', $ticket->encounter_id);
            }

            return redirect()->route('workspace.clinical');
        }

        // 2. Procedure / Injection Desk -> Route to Procedure Workspace
        if ($ticket->current_service_point === 'Procedure' && ($authService->hasPermission($user, 'procedure.order.view') || $authService->hasPermission($user, 'procedure.execute.dressing') || $authService->hasPermission($user, 'procedure.order.execute'))) {
            return redirect()->route('procedures.workspace');
        }

        // 3. Triage Desk -> Route to Clinical Workspace Vitals Tab
        if ($ticket->current_service_point === 'Triage' && ($authService->hasPermission($user, 'clinical.vitals.record') || $authService->hasPermission($user, 'clinical.encounter.view'))) {
            return redirect()->route('workspace.clinical', ['tab' => 'vitals', 'patient_id' => $ticket->patient_id]);
        }

        // 4. Pharmacy Desk -> Route to Pharmacy Dispensary Queue
        if ($ticket->current_service_point === 'Pharmacy' && ($authService->hasPermission($user, 'pharmacy.dispense.execute') || $authService->hasPermission($user, 'pharmacy.dispense.view'))) {
            return redirect()->route('pharmacy.queue');
        }

        // 5. Laboratory Desk -> Route to Laboratory Workspace
        if ($ticket->current_service_point === 'Lab' && ($authService->hasPermission($user, 'lab.specimen.collect') || $authService->hasPermission($user, 'lab.result.enter') || $authService->hasPermission($user, 'lab.test.view'))) {
            return redirect()->route('laboratory.workspace');
        }

        // 6. Cashier Desk -> Route to Billing POS Desk
        if ($ticket->current_service_point === 'Cashier' && ($authService->hasPermission($user, 'billing.payment.collect') || $authService->hasPermission($user, 'billing.invoice.view'))) {
            return redirect()->route('billing.desk');
        }

        return back()->with('success', "Ticket {$ticket->ticket_number} marked as In Progress at {$ticket->current_service_point} desk.");
    }
}
