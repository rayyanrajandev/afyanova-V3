<?php

use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Scheduling\Actions\BookAppointmentAction;
use App\Domains\Scheduling\Actions\CheckInPatientAction;
use App\Domains\Scheduling\Actions\TransferQueueAction;
use App\Domains\Scheduling\Exceptions\SchedulingConflictException;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\ProviderSchedule;
use App\Domains\Scheduling\Models\QueueTicket;
use Carbon\Carbon;

test('appointment can be booked and patient checked in to live queue', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $facility = $env['facility'];

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Rashid',
        'last_name' => 'Abdallah',
        'gender' => 'Male',
    ]);

    // 1. Create a provider schedule (available today)
    $today = now();
    ProviderSchedule::create([
        'provider_id' => $user->id,
        'facility_id' => $facility->id,
        'day_of_week' => $today->dayOfWeek,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'is_active' => true,
    ]);

    $bookAction = app(BookAppointmentAction::class);
    $appointment = $bookAction->execute([
        'patient_id' => $patient->id,
        'facility_id' => $facility->id,
        'provider_id' => $user->id,
        'scheduled_time' => $today->copy()->setTime(10, 0, 0),
        'duration_minutes' => 30,
        'appointment_type' => 'Consultation',
    ]);

    expect($appointment)->toBeInstanceOf(Appointment::class)
        ->and($appointment->status)->toBe('Scheduled');

    // 2. Check in patient
    $checkInAction = app(CheckInPatientAction::class);
    $ticket = $checkInAction->execute($appointment);

    $appointment->refresh();

    expect($appointment->status)->toBe('Checked-In')
        ->and($ticket)->toBeInstanceOf(QueueTicket::class)
        ->and($ticket->current_service_point)->toBe('Triage')
        ->and($ticket->status)->toBe('Waiting')
        ->and($ticket->encounter_id)->not->toBeNull();
});

test('scheduling detects conflicts when provider is double-booked', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $facility = $env['facility'];

    $patient1 = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'gender' => 'Female',
    ]);

    $patient2 = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Bob',
        'last_name' => 'Jones',
        'gender' => 'Male',
    ]);

    $targetTime = now()->next(Carbon::MONDAY)->setTime(9, 0, 0);

    ProviderSchedule::create([
        'provider_id' => $user->id,
        'facility_id' => $facility->id,
        'day_of_week' => Carbon::MONDAY,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'is_active' => true,
    ]);

    $bookAction = app(BookAppointmentAction::class);

    // First booking succeeds
    $bookAction->execute([
        'patient_id' => $patient1->id,
        'facility_id' => $facility->id,
        'provider_id' => $user->id,
        'scheduled_time' => $targetTime,
        'duration_minutes' => 30,
        'appointment_type' => 'Initial',
    ]);

    // Second booking at the exact same time must throw SchedulingConflictException
    expect(fn () => $bookAction->execute([
        'patient_id' => $patient2->id,
        'facility_id' => $facility->id,
        'provider_id' => $user->id,
        'scheduled_time' => $targetTime,
        'duration_minutes' => 30,
        'appointment_type' => 'Follow-up',
    ]))->toThrow(SchedulingConflictException::class);
});

test('live queue tickets can be transferred between service points', function () {
    $env = $this->setupTenantEnvironment();
    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Salma',
        'last_name' => 'Kikwete',
        'gender' => 'Female',
    ]);

    $ticket = QueueTicket::create([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'ticket_number' => 'B-201',
        'priority' => 'Emergency',
        'current_service_point' => 'Triage',
        'status' => 'Waiting',
    ]);

    $transferAction = app(TransferQueueAction::class);
    $transferred = $transferAction->execute($ticket, 'Doctor Room 1');

    expect($transferred->current_service_point)->toBe('Doctor Room 1')
        ->and($transferred->status)->toBe('Waiting');
});
