<?php

use App\Core\Context\BreakGlassContext;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Actions\AssignUserRoleAction;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Scheduling\Actions\BookAppointmentAction;
use App\Domains\Scheduling\Actions\CheckInPatientAction;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Hash;

/**
 * Proves HasFacilityScope (app/Core/Traits/HasFacilityScope.php) actually
 * restricts cross-facility visibility on Appointment/QueueTicket/Encounter/
 * Invoice for a facility-scoped user, the way Patient's own scope already
 * does — closing the gap the security audit found: these four models had
 * only tenant-level isolation, with facility isolation resting entirely on
 * individual controllers remembering to pass facility_id into permission
 * checks.
 *
 * One tenant, two facilities. CheckInPatientAction conveniently produces
 * one of each target model (appointment, queue ticket, encounter, invoice)
 * per facility in a single call, all tagged with the same facility_id.
 */
function buildTwoFacilityFixture(): array
{
    $tenant = Tenant::create([
        'name' => 'Two-Facility Hospital',
        'slug' => 'two-facility-'.uniqid(),
        'domain' => 'two-facility-'.uniqid().'.local',
        'status' => 'active',
    ]);

    setTestTenantContext($tenant->id);

    $facilityA = Facility::create(['tenant_id' => $tenant->id, 'name' => 'Facility A', 'code' => 'FAC-A', 'is_active' => true]);
    $facilityB = Facility::create(['tenant_id' => $tenant->id, 'name' => 'Facility B', 'code' => 'FAC-B', 'is_active' => true]);

    return [
        'tenant' => $tenant,
        'facilityA' => $facilityA,
        'facilityB' => $facilityB,
        'a' => buildFacilityRecords($facilityA, 'A'),
        'b' => buildFacilityRecords($facilityB, 'B'),
    ];
}

function buildFacilityRecords(Facility $facility, string $label): array
{
    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Patient',
        'last_name' => $label,
        'gender' => 'Female',
    ]);

    $appointment = app(BookAppointmentAction::class)->execute([
        'patient_id' => $patient->id,
        'facility_id' => $facility->id,
        'scheduled_time' => now()->addDay(),
        'duration_minutes' => 30,
        'appointment_type' => 'Consultation',
    ]);

    $queueTicket = app(CheckInPatientAction::class)->execute($appointment->fresh());

    return [
        'patient' => $patient,
        'appointment' => $appointment->fresh(),
        'queueTicket' => $queueTicket,
        'encounter' => Encounter::find($queueTicket->encounter_id),
        'invoice' => Invoice::where('patient_id', $patient->id)->firstOrFail(),
    ];
}

function actingAsFacilityScopedUser(array $fixture, string $facilityId): User
{
    $role = Role::create(['tenant_id' => $fixture['tenant']->id, 'slug' => 'facility-scoped-'.uniqid(), 'name' => 'Facility Scoped Test Role']);
    $user = User::create([
        'tenant_id' => $fixture['tenant']->id,
        'first_name' => 'Scoped',
        'last_name' => 'User',
        'email' => 'scoped-'.uniqid().'@two-facility.local',
        'password_hash' => Hash::make('password123'),
        'status' => 'active',
    ]);
    app(AssignUserRoleAction::class)->execute($user->id, $role->id, $facilityId);

    test()->actingAs($user);

    return $user;
}

test('a facility-scoped user cannot see another facility\'s appointments, queue tickets, encounters, or invoices', function () {
    $fixture = buildTwoFacilityFixture();
    actingAsFacilityScopedUser($fixture, $fixture['facilityA']->id);

    $appointmentIds = Appointment::pluck('id');
    expect($appointmentIds)->toContain($fixture['a']['appointment']->id);
    expect($appointmentIds)->not->toContain($fixture['b']['appointment']->id);

    $queueTicketIds = QueueTicket::pluck('id');
    expect($queueTicketIds)->toContain($fixture['a']['queueTicket']->id);
    expect($queueTicketIds)->not->toContain($fixture['b']['queueTicket']->id);

    $encounterIds = Encounter::pluck('id');
    expect($encounterIds)->toContain($fixture['a']['encounter']->id);
    expect($encounterIds)->not->toContain($fixture['b']['encounter']->id);

    $invoiceIds = Invoice::pluck('id');
    expect($invoiceIds)->toContain($fixture['a']['invoice']->id);
    expect($invoiceIds)->not->toContain($fixture['b']['invoice']->id);
});

test('a user with a global (unscoped) role assignment sees every facility', function () {
    $fixture = buildTwoFacilityFixture();

    $role = Role::create(['tenant_id' => $fixture['tenant']->id, 'slug' => 'tenant-admin-'.uniqid(), 'name' => 'Global Test Role']);
    $user = User::create([
        'tenant_id' => $fixture['tenant']->id,
        'first_name' => 'Global',
        'last_name' => 'User',
        'email' => 'global-'.uniqid().'@two-facility.local',
        'password_hash' => Hash::make('password123'),
        'status' => 'active',
    ]);
    app(AssignUserRoleAction::class)->execute($user->id, $role->id); // no facility_id => global assignment

    $this->actingAs($user);

    $appointmentIds = Appointment::pluck('id');
    expect($appointmentIds)->toContain($fixture['a']['appointment']->id);
    expect($appointmentIds)->toContain($fixture['b']['appointment']->id);
});

test('break-glass restores visibility of one patient\'s records at another facility, and nothing else', function () {
    $fixture = buildTwoFacilityFixture();
    actingAsFacilityScopedUser($fixture, $fixture['facilityA']->id);

    // Sanity: without break-glass, Facility B is invisible.
    expect(Invoice::pluck('id'))->not->toContain($fixture['b']['invoice']->id);
    expect(Encounter::pluck('id'))->not->toContain($fixture['b']['encounter']->id);

    app(BreakGlassContext::class)->setPatientId($fixture['b']['patient']->id);

    expect(Invoice::pluck('id'))->toContain($fixture['b']['invoice']->id);
    expect(Encounter::pluck('id'))->toContain($fixture['b']['encounter']->id);
    expect(Appointment::pluck('id'))->toContain($fixture['b']['appointment']->id);
    expect(QueueTicket::pluck('id'))->toContain($fixture['b']['queueTicket']->id);
});

test('a user with no facility-scoped role assignment at all is not restricted', function () {
    $fixture = buildTwoFacilityFixture();

    // A user who exists in the tenant but holds no role assignment
    // whatsoever — HasFacilityScope is deliberately permissive here,
    // mirroring Patient::booted()'s own documented edge-case behavior.
    $user = User::create([
        'tenant_id' => $fixture['tenant']->id,
        'first_name' => 'Roleless',
        'last_name' => 'User',
        'email' => 'roleless-'.uniqid().'@two-facility.local',
        'password_hash' => Hash::make('password123'),
        'status' => 'active',
    ]);

    $this->actingAs($user);

    $appointmentIds = Appointment::pluck('id');
    expect($appointmentIds)->toContain($fixture['a']['appointment']->id);
    expect($appointmentIds)->toContain($fixture['b']['appointment']->id);
});
