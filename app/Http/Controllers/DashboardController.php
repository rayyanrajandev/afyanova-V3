<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // 1. Hospital Enterprise Metrics (Real DB Data)
        $activeEncountersCount = Encounter::where('status', '!=', 'Closed')->count();
        $activePatientIds = Encounter::where('status', '!=', 'Closed')->pluck('patient_id')->filter()->unique()->toArray();
        $waitingPatientsCount = Patient::whereNotIn('id', $activePatientIds)->count();
        $todayRevenue = (float) Invoice::whereDate('created_at', today())->sum('paid_amount');
        if ($todayRevenue <= 0) {
            $todayRevenue = (float) Invoice::sum('paid_amount');
        }

        $metrics = [
            'total_patients' => Patient::count(),
            'active_encounters' => $activeEncountersCount,
            'today_appointments' => Appointment::count(),
            'queue_waiting' => $waitingPatientsCount,
            'pending_pharmacy' => Prescription::whereIn('status', ['Prescribed', 'Verified', 'Pending'])->count(),
            'unpaid_invoices' => Invoice::whereIn('status', ['Issued', 'Partial', 'Unpaid'])->count(),
            'today_revenue' => $todayRevenue,
        ];

        // 2. Real Lists for Hospital Dashboard
        $recentEncounters = Encounter::with(['patient.allergies', 'provider', 'vitals', 'notes', 'diagnoses', 'prescriptions.medication', 'labOrders.items.labTest'])
            ->where('status', '!=', 'Closed')
            ->latest('created_at')
            ->take(20)
            ->get();

        $recentPatients = Patient::with(['allergies', 'latestVital'])
            ->latest('created_at')
            ->take(20)
            ->get();

        $recentInvoices = Invoice::with(['patient', 'items'])
            ->latest('created_at')
            ->take(20)
            ->get();

        $queueTickets = QueueTicket::with(['patient.allergies', 'encounter'])
            ->where('status', '!=', 'Completed')
            ->orderBy('priority', 'desc')
            ->latest('joined_queue_at')
            ->take(20)
            ->get();

        $todayAppointments = Appointment::with(['patient', 'practitioner', 'department'])
            ->latest('scheduled_time')
            ->take(20)
            ->get();

        $pendingPrescriptions = Prescription::with(['encounter.patient', 'medication', 'prescriber'])
            ->whereIn('status', ['Prescribed', 'Verified', 'Pending'])
            ->latest('created_at')
            ->take(20)
            ->get();

        return Inertia::render('Workspace/HomeWorkspace', [
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
