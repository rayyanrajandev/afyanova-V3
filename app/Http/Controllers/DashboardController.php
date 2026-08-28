<?php

namespace App\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService): Response|RedirectResponse
    {
        $user = $request->user();

        // 1. Platform Superadmins land directly on Multi-Tenant SaaS Control Plane
        if ($authService->isSuperAdmin($user) && ! $request->session()->has('impersonation')) {
            return redirect()->route('superadmin.workspace');
        }

        // 2. Hospital / Tenant Administrators land on Executive Operations Command Center
        if ($authService->isTenantAdmin($user)) {
            return $this->renderExecutiveDashboard($request, $user, $authService);
        }

        // 3. Hospital Staff (Clinicians, Cashiers, Receptionists, Pharmacists) -> Front-Desk & Operational Dashboard
        return $this->renderFrontDeskDashboard($request, $user, $authService);
    }

    /**
     * Render the Executive Operations Command Center for Hospital/Tenant Administrators.
     */
    protected function renderExecutiveDashboard(Request $request, User $user, AuthorizationService $authService): Response
    {
        $tenant = $user->tenant;

        // Financial Collections & Revenue Breakdown
        $todayRevenue = (float) Invoice::whereDate('created_at', today())->sum('paid_amount');
        if ($todayRevenue <= 0) {
            $todayRevenue = (float) Invoice::sum('paid_amount');
        }

        $paymentsByMethod = Payment::whereDate('created_at', today())
            ->selectRaw('payment_method, sum(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->toArray();

        $unpaidInvoicesCount = Invoice::whereIn('status', ['Draft', 'Issued', 'Partially_Paid', 'PartiallyPaid', 'Partially Paid', 'Pending', 'Open', 'Unpaid'])->count();
        $unpaidInvoicesAmount = (float) Invoice::whereIn('status', ['Draft', 'Issued', 'Partially_Paid', 'PartiallyPaid', 'Partially Paid', 'Pending', 'Open', 'Unpaid'])
            ->selectRaw('sum(total_amount - paid_amount) as outstanding')
            ->value('outstanding');

        // Hospital Census & Inpatient Utilization
        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'Occupied')->count();
        $availableBeds = Bed::where('status', 'Available')->count();
        $bedOccupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

        // Clinical Volume & Department Flow Today
        $todayEncounters = Encounter::whereDate('created_at', today())->count();
        $activeEncounters = Encounter::whereIn('status', ['In Progress', 'In_Progress', 'Waiting', 'Triage'])->count();
        $todayAdmissions = Encounter::whereDate('created_at', today())->where('encounter_type', 'Inpatient')->count();
        $todayEmergency = Encounter::whereDate('created_at', today())->where('encounter_type', 'Emergency')->count();

        // Department Queue Pressures
        $pointCounts = [
            'Triage' => QueueTicket::where('current_service_point', 'Triage')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
            'Doctor' => QueueTicket::where('current_service_point', 'Doctor')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
            'Procedure' => QueueTicket::where('current_service_point', 'Procedure')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
            'Lab' => QueueTicket::where('current_service_point', 'Lab')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
            'Pharmacy' => QueueTicket::where('current_service_point', 'Pharmacy')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
            'Cashier' => QueueTicket::where('current_service_point', 'Cashier')->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->count(),
        ];

        // Quota & Fleet Status
        $facilitiesCount = Facility::count();
        $usersCount = User::count();
        $planBlueprint = SubscriptionPlan::where('code', $tenant?->subscription_tier)->first();

        $maxUsers = $tenant?->max_users ?? $planBlueprint?->max_users ?? 50;
        $maxFacilities = $tenant?->max_facilities ?? $planBlueprint?->max_facilities ?? 5;
        $storageQuota = $tenant?->storage_quota_mb ?? $planBlueprint?->storage_quota_mb ?? 10240;

        // Inventory Alerts (Items below reorder level)
        $lowStockCount = InventoryStockBalance::where('quantity_on_hand', '<=', 10)->count();

        // Forensic Security & Audit Stream
        $recentAudits = AuditLog::latest('created_at')
            ->take(10)
            ->get(['id', 'event_category', 'action', 'entity_type', 'ip_address', 'created_at']);

        // Facility Branches List
        $facilities = Facility::withCount('departments')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'facility_type', 'city', 'region', 'is_active']);

        return Inertia::render('Workspace/HospitalExecutiveWorkspace', [
            'executive' => [
                'tenant' => [
                    'id' => $tenant?->id,
                    'name' => $tenant?->name,
                    'slug' => $tenant?->slug,
                    'plan' => $tenant?->plan ?? $tenant?->subscription_tier ?? 'Growth Tier',
                    'subscription_status' => $tenant?->subscription_status ?? 'active',
                    'features' => $tenant?->feature_flags ?? [],
                ],
                'quotas' => [
                    'users_used' => $usersCount,
                    'max_users' => $maxUsers,
                    'facilities_used' => $facilitiesCount,
                    'max_facilities' => $maxFacilities,
                    'storage_used_mb' => 245,
                    'storage_quota_mb' => $storageQuota,
                ],
                'finance' => [
                    'today_revenue' => $todayRevenue,
                    'unpaid_invoices_count' => $unpaidInvoicesCount,
                    'unpaid_invoices_amount' => $unpaidInvoicesAmount,
                    'payment_breakdown' => $paymentsByMethod,
                ],
                'census' => [
                    'total_beds' => $totalBeds,
                    'occupied_beds' => $occupiedBeds,
                    'available_beds' => $availableBeds,
                    'occupancy_rate' => $bedOccupancyRate,
                ],
                'workload' => [
                    'today_encounters' => $todayEncounters,
                    'active_encounters' => $activeEncounters,
                    'today_admissions' => $todayAdmissions,
                    'today_emergency' => $todayEmergency,
                    'point_counts' => $pointCounts,
                ],
                'alerts' => [
                    'low_stock_count' => $lowStockCount,
                ],
                'recent_audits' => $recentAudits,
                'facilities' => $facilities,
            ],
        ]);
    }

    /**
     * Render the Front-Desk Reception, Intake & Triage Workspace.
     */
    protected function renderFrontDeskDashboard(Request $request, User $user, AuthorizationService $authService): Response
    {
        // Authorize that user has at least one valid hospital permission or is tenant admin
        $this->authorizeAnyWorkspacePermission($user, $authService, [
            'scheduling.queue.view',
            'scheduling.appointment.view',
            'patient.registry.view',
            'clinical.encounter.view',
            'billing.invoice.view',
            'pharmacy.prescription.view',
            'lab.order.view',
            'procedure.order.view',
            'radiology.order.view',
            'insurance.claim.view',
            'inventory.stock.view',
            'reports.analytics.view',
            'identity.user.manage',
        ]);

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

        // Front-Desk & Reception Operational Metrics
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
