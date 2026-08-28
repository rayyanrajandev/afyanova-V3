<?php

namespace App\Domains\Scheduling\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Patient\Models\Patient;
use App\Domains\Scheduling\Actions\BookAppointmentAction;
use App\Domains\Scheduling\Actions\CheckInPatientAction;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Exceptions\SchedulingConflictException;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Tenancy\Models\Department;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['scheduling.appointment.view']);

        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'store' => 'scheduling.appointment.create',
            'checkIn' => 'scheduling.appointment.checkin',
            'patients' => 'patient.registry.view',
            'queue' => 'scheduling.queue.view',
        ]);

        $appointments = Appointment::with(['patient', 'provider', 'facility', 'department'])
            ->whereDate('scheduled_time', '>=', now()->toDateString())
            ->orderBy('scheduled_time')
            ->get();

        $patients = $can['patients']
            ? Patient::select('id', 'first_name', 'last_name', 'primary_mrn')
                ->latest()
                ->take(100)
                ->get()
            : collect();

        $providers = User::select('id', 'first_name', 'last_name', 'email')
            ->whereHas('roleAssignments.role', fn ($q) => $q->where('slug', 'doctor'))
            ->get();

        $facilities = Facility::select('id', 'name')->get();
        $departments = Department::select('id', 'name')->get();

        $metrics = [
            'total_patients' => $can['patients'] ? Patient::count() : null,
            'lobby_waiting' => QueueTicket::whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->whereDate('created_at', today())->count(),
            'today_appointments' => Appointment::whereDate('scheduled_time', today())->where('status', 'Scheduled')->count(),
        ];

        return Inertia::render('Domains/Scheduling/Calendar', [
            'can' => $can,
            'appointments' => $appointments,
            'patients' => $patients,
            'providers' => $providers,
            'facilities' => $facilities,
            'departments' => $departments,
            'metrics' => $metrics,
        ]);
    }

    public function store(Request $request, BookAppointmentAction $action)
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'facility_id' => 'required|string',
            'department_id' => 'nullable|string',
            'provider_id' => 'nullable|string',
            'scheduled_time' => 'required|date|after_or_equal:today',
            'duration_minutes' => 'required|integer|min:5',
            'appointment_type' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $this->authorize('create', [Appointment::class, $validated['facility_id']]);

        try {
            $action->execute($validated);

            return back()->with('success', 'Appointment booked successfully.');
        } catch (SchedulingConflictException $e) {
            return back()->withErrors(['schedule' => $e->getMessage()]);
        }
    }

    public function checkIn(Appointment $appointment, CheckInPatientAction $action)
    {
        $this->authorize('checkIn', $appointment);

        $action->execute($appointment);

        return redirect()->route('queue.index')->with('success', 'Patient checked in successfully. Encounter started.');
    }
}
