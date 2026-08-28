<?php

use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\Permission;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Scheduling\Actions\BookAppointmentAction;
use App\Domains\Scheduling\Actions\CheckInPatientAction;
use App\Domains\Scheduling\Actions\TransferQueueAction;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
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
        ->and($ticket->status)->toBe(QueueTicketStatus::Waiting)
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
        'status' => QueueTicketStatus::Waiting,
    ]);

    $transferAction = app(TransferQueueAction::class);
    $transferred = $transferAction->execute($ticket, 'Doctor Room 1');

    expect($transferred->current_service_point)->toBe('Doctor Room 1')
        ->and($transferred->status)->toBe(QueueTicketStatus::Waiting);
});

test('calling queue tickets automatically redirects to respective workstation', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $facility = $env['facility'];

    $role = $user->roleAssignments->first()->role;
    $perms = [
        'scheduling.queue.call' => ['name' => 'Call Queue Ticket', 'domain' => 'Scheduling'],
        'procedure.order.view' => ['name' => 'View Procedure Orders', 'domain' => 'Procedure'],
        'procedure.execute.dressing' => ['name' => 'Execute Procedure', 'domain' => 'Procedure'],
        'clinical.vitals.record' => ['name' => 'Record Vital Signs', 'domain' => 'Clinical'],
    ];

    foreach ($perms as $slug => $attrs) {
        $p = Permission::firstOrCreate(['slug' => $slug], $attrs);
        $role->permissions()->syncWithoutDetaching([$p->id]);
    }

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Hawa',
        'last_name' => 'Said',
        'gender' => 'Female',
    ]);

    // 1. Call Procedure Desk
    $procTicket = QueueTicket::create([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $facility->id,
        'ticket_number' => 'PRC-101',
        'priority' => 'Routine',
        'current_service_point' => 'Procedure',
        'status' => QueueTicketStatus::Waiting,
    ]);

    $this->actingAs($user)
        ->post(route('queue.call', $procTicket->id))
        ->assertRedirect(route('procedures.workspace'));

    expect($procTicket->fresh()->status)->toBe(QueueTicketStatus::InProgress);

    // 2. Call Triage Desk
    $triageTicket = QueueTicket::create([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $facility->id,
        'ticket_number' => 'TRG-102',
        'priority' => 'Routine',
        'current_service_point' => 'Triage',
        'status' => QueueTicketStatus::Waiting,
    ]);

    $this->actingAs($user)
        ->post(route('queue.call', $triageTicket->id))
        ->assertRedirect(route('workspace.clinical', ['tab' => 'vitals', 'patient_id' => $patient->id]));

    expect($triageTicket->fresh()->status)->toBe(QueueTicketStatus::InProgress);
});

test('checkInDirect generates accurate pre-service cash invoices for sindano ya nje vs clinic stock vs prepaid revisit', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $facility = $env['facility'];
    $role = $user->roleAssignments->first()->role;

    $checkinPerm = Permission::firstOrCreate(
        ['slug' => 'scheduling.appointment.checkin'],
        ['name' => 'Check-in Appointment', 'domain' => 'Scheduling']
    );
    $role->permissions()->syncWithoutDetaching([$checkinPerm->id]);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Amina',
        'last_name' => 'Bakari',
        'gender' => 'Female',
    ]);

    $injCatalog = ProcedureCatalog::create([
        'tenant_id' => $env['tenant']->id,
        'procedure_code' => 'PROC-INJ-001',
        'name' => 'Intramuscular Injection',
        'category' => 'Injection',
        'tier_level' => 'Tier1_Minor',
        'default_duration_minutes' => 10,
        'standard_price' => 5000.00,
        'requires_consent' => false,
        'requires_anesthesia' => false,
        'is_active' => true,
    ]);

    // 1. Check in for Sindano ya Nje (Patient-Supplied) -> TZS 2,000 nursing admin fee invoice
    $response = $this->actingAs($user)->post(route('queue.checkin-direct'), [
        'patient_id' => $patient->id,
        'service_point' => 'Procedure',
        'visit_type' => 'Procedure',
        'medication_source' => 'PatientSupplied',
        'procedure_catalog_id' => $injCatalog->id,
        'payment_mode' => 'Cash',
        'priority' => 'Routine',
        'reason' => 'Sindano ya Nje (Depo Provera)',
    ]);

    $response->assertSessionHasNoErrors();
    $invoice1 = Invoice::where('patient_id', $patient->id)->latest()->first();
    expect($invoice1)->not->toBeNull()
        ->and((float) $invoice1->total_amount)->toBe(2000.00)
        ->and($invoice1->status)->toBe('Issued');

    // 2. Check in for Course Revisit (Dose #2 of 5) -> Prepaid TZS 0 invoice
    $response2 = $this->actingAs($user)->post(route('queue.checkin-direct'), [
        'patient_id' => $patient->id,
        'service_point' => 'Procedure',
        'visit_type' => 'Treatment_Followup',
        'medication_source' => 'ClinicStock',
        'procedure_catalog_id' => $injCatalog->id,
        'payment_mode' => 'Prepaid',
        'priority' => 'Routine',
        'reason' => 'Dose #2 of 5 Revisit',
    ]);

    $response2->assertSessionHasNoErrors();
    // Verify no new cash invoice generated for prepaid follow-up
    $invoicesCount = Invoice::where('patient_id', $patient->id)->count();
    expect($invoicesCount)->toBe(1); // Only the first invoice exists

    // 3. Check in for Wound Dressing (Kidonda) -> Charges catalog price
    $drsCatalog = ProcedureCatalog::create([
        'tenant_id' => $env['tenant']->id,
        'procedure_code' => 'PROC-DRS-001',
        'name' => 'Wound Debridement & Sterile Dressing',
        'category' => 'Dressing',
        'tier_level' => 'Tier1_Minor',
        'default_duration_minutes' => 20,
        'standard_price' => 15000.00,
        'requires_consent' => false,
        'requires_anesthesia' => false,
        'is_active' => true,
    ]);

    $response3 = $this->actingAs($user)->post(route('queue.checkin-direct'), [
        'patient_id' => $patient->id,
        'service_point' => 'Procedure',
        'visit_type' => 'Procedure',
        'medication_source' => 'ClinicStock',
        'procedure_catalog_id' => $drsCatalog->id,
        'payment_mode' => 'Cash',
        'priority' => 'Routine',
        'reason' => 'Deep laceration wound dressing',
    ]);

    $response3->assertSessionHasNoErrors();
    $invoice3 = Invoice::where('patient_id', $patient->id)->where('id', '!=', $invoice1->id)->first();
    expect($invoice3)->not->toBeNull()
        ->and((float) $invoice3->total_amount)->toBe(15000.00)
        ->and($invoice3->status)->toBe('Issued');
});

test('queue call controller blocks routine cash tickets with unpaid invoices and allows calling once paid', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $facility = $env['facility'];
    $role = $user->roleAssignments->first()->role;

    $perms = [
        'scheduling.queue.call' => ['name' => 'Call Queue Ticket', 'domain' => 'Scheduling'],
        'procedure.order.view' => ['name' => 'View Procedure Orders', 'domain' => 'Procedure'],
        'procedure.execute.dressing' => ['name' => 'Execute Procedure', 'domain' => 'Procedure'],
    ];
    foreach ($perms as $slug => $attrs) {
        $p = Permission::firstOrCreate(['slug' => $slug], $attrs);
        $role->permissions()->syncWithoutDetaching([$p->id]);
    }

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Kassim',
        'last_name' => 'Majaliwa',
        'gender' => 'Male',
    ]);

    $encounter = Encounter::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $facility->id,
        'patient_id' => $patient->id,
        'encounter_type' => 'Procedure',
        'reason_for_visit' => 'Wound care dressing',
        'status' => 'In Progress',
        'start_time' => now(),
    ]);

    $ticket = QueueTicket::create([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'encounter_id' => $encounter->id,
        'facility_id' => $facility->id,
        'ticket_number' => 'PRC-555',
        'priority' => 'Routine',
        'current_service_point' => 'Procedure',
        'status' => QueueTicketStatus::Waiting,
    ]);

    // 1. Create unpaid invoice
    $invoice = Invoice::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $facility->id,
        'patient_id' => $patient->id,
        'encounter_id' => $encounter->id,
        'invoice_number' => 'INV-TEST-555',
        'status' => 'Issued',
        'total_amount' => 15000.00,
        'paid_amount' => 0.00,
        'issue_date' => now(),
    ]);

    // 2. Call ticket while unpaid -> Must be blocked
    $response = $this->actingAs($user)->post(route('queue.call', $ticket->id));
    $response->assertSessionHasErrors(['call']);
    expect($ticket->fresh()->status)->toBe(QueueTicketStatus::Waiting);

    // 3. Mark invoice as Paid
    $invoice->update([
        'status' => 'Paid',
        'paid_amount' => 15000.00,
    ]);

    // 4. Retry call -> Must succeed and redirect to procedure workspace
    $responsePaid = $this->actingAs($user)->post(route('queue.call', $ticket->id));
    $responsePaid->assertRedirect(route('procedures.workspace'));
    expect($ticket->fresh()->status)->toBe(QueueTicketStatus::InProgress);
});
