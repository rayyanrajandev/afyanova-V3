<?php

namespace App\Domains\Scheduling\Http\Controllers;

use App\Domains\Scheduling\Actions\BookAppointmentAction;
use App\Domains\Scheduling\Actions\CheckInPatientAction;
use App\Domains\Scheduling\Exceptions\SchedulingConflictException;
use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $appointments = Appointment::with(['patient', 'provider'])
            ->whereDate('scheduled_time', '>=', now()->toDateString())
            ->orderBy('scheduled_time')
            ->get();

        return Inertia::render('Domains/Scheduling/Calendar', [
            'appointments' => $appointments,
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
