<?php

namespace App\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $user = $request->user();

        $can = $this->buildSectionCanMap($user, $authService, [
            'clinical' => 'clinical.encounter.view',
            'queue' => 'scheduling.queue.view',
            'appointments' => 'scheduling.appointment.view',
            'checkin' => 'scheduling.appointment.checkin',
            'pharmacy' => 'pharmacy.prescription.view',
            'lab' => 'lab.order.view',
            'procedure' => 'procedure.order.view',
            'billing' => 'billing.invoice.view',
            'patients' => 'patient.registry.view',
            'create_patient' => 'patient.registry.create',
            'create_note' => 'clinical.notes.create',
            'breakGlass' => 'clinical.break_glass',
        ]);

        // 2. High-Efficiency Operational Front-Desk & Facility Metrics
        $todayRegisteredCount = Patient::whereDate('created_at', today())->count();
        $totalPatientsCount = Patient::count();
        $todayInflowCount = QueueTicket::whereDate('created_at', today())->count();

        $todayTotalAppointments = Appointment::whereDate('scheduled_time', today())->count();
        $todayPendingAppointments = Appointment::whereDate('scheduled_time', today())
            ->where('status', 'Scheduled')
            ->count();

        $checkedInAppointmentsCount = Appointment::whereDate('scheduled_time', today())
            ->whereIn('status', ['Checked-In', 'In Progress', 'Completed'])
            ->count();

        $lobbyWaitingCount = QueueTicket::whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count();

        $todayRevenue = (float) Invoice::whereDate('created_at', today())->sum('paid_amount');
        if ($todayRevenue <= 0) {
            $todayRevenue = (float) Invoice::sum('paid_amount');
        }

        $unpaidInvoicesCount = Invoice::whereIn('status', ['Draft', 'Issued', 'Partially_Paid', 'PartiallyPaid', 'Partially Paid', 'Pending', 'Open', 'Unpaid'])->count();

        // Live Queue Count Breakdown by Service Desk
        $pointCounts = [
            'Triage' => QueueTicket::where('current_service_point', 'Triage')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
            'Doctor' => QueueTicket::where('current_service_point', 'Doctor')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
            'Procedure' => QueueTicket::where('current_service_point', 'Procedure')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
            'Lab' => QueueTicket::where('current_service_point', 'Lab')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
            'Pharmacy' => QueueTicket::where('current_service_point', 'Pharmacy')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
            'Cashier' => QueueTicket::where('current_service_point', 'Cashier')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
        ];

        $metrics = [
            'active_encounters' => $can['clinical'] ? Encounter::whereIn('status', ['In Progress', 'In_Progress', 'Waiting', 'Triage'])->count() : null,
            'queue_waiting' => $can['queue'] ? QueueTicket::where('status', QueueTicketStatus::Waiting)->count() : null,
            'pending_pharmacy' => $can['pharmacy'] ? Prescription::where('status', 'Pending')->count() : null,
            'pending_lab_orders' => $can['lab'] ? LabOrderItem::whereIn('status', ['Ordered', 'Pending', 'Sample_Collected'])->count() : null,
            'today_inflow' => $can['queue'] ? $todayInflowCount : null,
            'today_registered' => $can['patients'] ? $todayRegisteredCount : null,
            'total_patients' => $can['patients'] ? $totalPatientsCount : null,
            'today_total_appointments' => $can['appointments'] ? $todayTotalAppointments : null,
            'today_appointments' => $can['appointments'] ? $todayPendingAppointments : null,
            'checked_in_appointments' => $can['appointments'] ? $checkedInAppointmentsCount : null,
            'lobby_waiting' => $can['queue'] ? $lobbyWaitingCount : null,
            'point_counts' => $can['queue'] ? $pointCounts : null,
            'today_revenue' => $can['billing'] ? $todayRevenue : null,
            'unpaid_invoices' => $can['billing'] ? $unpaidInvoicesCount : null,
        ];

        // 3. Operational Real Lists
        // Note: no clinical relations (allergies, vitals) are eager-loaded here — this
        // is a front-desk/queue summary widget, not a clinical chart view, and
        // resources/js/Pages/Workspace/HomeWorkspace.vue never reads them. Roles gated
        // in on demographics access (e.g. Receptionist, Cashier) are not necessarily
        // granted clinical/allergy access per the confidentiality matrix, so fetching
        // clinical sub-relations here would over-expose them regardless of UI usage.
        $todayAppointments = $can['appointments']
            ? Appointment::with(['patient', 'provider', 'department'])
                ->whereDate('scheduled_time', today())
                ->orderBy('scheduled_time', 'asc')
                ->take(30)
                ->get()
            : collect();

        $recentPatients = $can['patients']
            ? Patient::latest('created_at')
                ->take(15)
                ->get()
            : collect();

        $activeQueueTickets = $can['queue']
            ? QueueTicket::with(['patient', 'encounter'])
                ->whereIn('status', ['Waiting', 'In Progress'])
                ->orderByRaw("CASE WHEN priority = 'Emergency' THEN 1 WHEN priority = 'Urgent' THEN 2 ELSE 3 END")
                ->latest('joined_queue_at')
                ->take(20)
                ->get()
            : collect();

        $quickPatients = $can['patients']
            ? Patient::where('status', 'Active')
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get(['id', 'primary_mrn', 'first_name', 'last_name', 'gender', 'dob', 'status'])
            : collect();

        $labTests = LabTest::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'test_code', 'name', 'price', 'category', 'specimen_type']);

        $procedureCatalogs = ProcedureCatalog::where('is_active', true)
            ->where('tier_level', 'Tier1_Minor')
            ->orderBy('name')
            ->get(['id', 'procedure_code', 'name', 'category', 'tier_level', 'standard_price']);

        return Inertia::render('Workspace/HomeWorkspace', [
            'can' => $can,
            'metrics' => $metrics,
            'todayAppointments' => $todayAppointments,
            'recentPatients' => $recentPatients,
            'activeQueueTickets' => $activeQueueTickets,
            'quickPatients' => $quickPatients,
            'labTests' => $labTests,
            'procedureCatalogs' => $procedureCatalogs,
        ]);
    }
}
