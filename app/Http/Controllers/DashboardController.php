<?php

namespace App\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\Prescription;
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
        // No hard page-level bar: every seeded role holds patient.registry.view,
        // so a blanket 403 here would be dead code. Section-level can map only.
        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'clinical' => 'clinical.encounter.view',
            'queue' => 'scheduling.queue.view',
            'appointments' => 'scheduling.appointment.view',
            'pharmacy' => 'pharmacy.prescription.view',
            'billing' => 'billing.invoice.view',
            'patients' => 'patient.registry.view',
        ]);

        // 1. Hospital Enterprise Metrics (Real DB Data)
        $activeEncountersCount = $can['clinical'] ? Encounter::where('status', '!=', 'Closed')->count() : null;
        $waitingPatientsCount = null;
        if ($can['patients']) {
            $activePatientIds = Encounter::where('status', '!=', 'Closed')->pluck('patient_id')->filter()->unique()->toArray();
            $waitingPatientsCount = Patient::whereNotIn('id', $activePatientIds)->count();
        }
        $todayRevenue = null;
        if ($can['billing']) {
            $todayRevenue = (float) Invoice::whereDate('created_at', today())->sum('paid_amount');
            if ($todayRevenue <= 0) {
                $todayRevenue = (float) Invoice::sum('paid_amount');
            }
        }

        $metrics = [
            'total_patients' => $can['patients'] ? Patient::count() : null,
            'active_encounters' => $activeEncountersCount,
            'today_appointments' => $can['appointments'] ? Appointment::count() : null,
            'queue_waiting' => $waitingPatientsCount,
            'pending_pharmacy' => $can['pharmacy'] ? Prescription::whereIn('status', ['Prescribed', 'Verified', 'Pending'])->count() : null,
            'unpaid_invoices' => $can['billing'] ? Invoice::whereIn('status', ['Issued', 'Partial', 'Unpaid'])->count() : null,
            'today_revenue' => $todayRevenue,
        ];

        // 2. Real Lists for Hospital Dashboard
        $recentEncounters = $can['clinical']
            ? Encounter::with(['patient.allergies', 'provider', 'vitals', 'notes', 'diagnoses', 'prescriptions.medication', 'labOrders.items.labTest'])
                ->where('status', '!=', 'Closed')
                ->latest('created_at')
                ->take(20)
                ->get()
            : collect();

        $recentPatients = $can['patients']
            ? Patient::with(['allergies', 'latestVital'])
                ->latest('created_at')
                ->take(20)
                ->get()
            : collect();

        $recentInvoices = $can['billing']
            ? Invoice::with(['patient', 'items'])
                ->latest('created_at')
                ->take(20)
                ->get()
            : collect();

        $queueTickets = $can['queue']
            ? QueueTicket::with(['patient.allergies', 'encounter'])
                ->where('status', '!=', 'Completed')
                ->orderBy('priority', 'desc')
                ->latest('joined_queue_at')
                ->take(20)
                ->get()
            : collect();

        $todayAppointments = $can['appointments']
            ? Appointment::with(['patient', 'practitioner', 'department'])
                ->latest('scheduled_time')
                ->take(20)
                ->get()
            : collect();

        $pendingPrescriptions = $can['pharmacy']
            ? Prescription::with(['encounter.patient', 'medication', 'prescriber'])
                ->whereIn('status', ['Prescribed', 'Verified', 'Pending'])
                ->latest('created_at')
                ->take(20)
                ->get()
            : collect();

        return Inertia::render('Workspace/HomeWorkspace', [
            'can' => $can,
            'metrics' => $metrics,
            'recentEncounters' => $recentEncounters,
            'recentPatients' => $recentPatients,
            'recentInvoices' => $recentInvoices,
            'queueTickets' => $queueTickets,
            'todayAppointments' => $todayAppointments,
            'pendingPrescriptions' => $pendingPrescriptions,
        ]);
    }
}
