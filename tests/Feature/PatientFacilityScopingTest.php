<?php

use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Identity\Actions\AssignUserRoleAction;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Hash;

/**
 * One tenant, two facilities, and a user assigned only to Facility A —
 * exercises Patient::booted()'s facility-visibility scope and the
 * break-glass override that's meant to bypass it in an emergency.
 */
function buildTwoFacilityTenant(): array
{
    $tenant = Tenant::create([
        'name' => 'Multi-Facility Hospital',
        'slug' => 'multi-facility-'.uniqid(),
        'domain' => 'multi-'.uniqid().'.local',
        'status' => 'active',
    ]);

    setTestTenantContext($tenant->id);

    $facilityA = Facility::create([
        'tenant_id' => $tenant->id,
        'name' => 'Facility A',
        'code' => 'FAC-A',
        'is_active' => true,
    ]);

    $facilityB = Facility::create([
        'tenant_id' => $tenant->id,
        'name' => 'Facility B',
        'code' => 'FAC-B',
        'is_active' => true,
    ]);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Nurse',
        'last_name' => 'AOnly',
        'email' => 'nurse-a-only-'.uniqid().'@example.local',
        'password_hash' => Hash::make('password123'),
        'status' => 'active',
    ]);

    $role = Role::create(['tenant_id' => $tenant->id, 'slug' => 'nurse', 'name' => 'Nurse']);
    $viewPermission = Permission::firstOrCreate(
        ['slug' => 'patient.registry.view'],
        ['name' => 'View Patient Registry', 'domain' => 'Patient']
    );
    $role->permissions()->syncWithoutDetaching([$viewPermission->id]);
    app(AssignUserRoleAction::class)->execute($user->id, $role->id, $facilityA->id);

    $patientAtA = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Amina', 'last_name' => 'AtFacilityA', 'gender' => 'Female',
        'facility_id' => $facilityA->id,
    ]);

    $patientAtB = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Baraka', 'last_name' => 'AtFacilityB', 'gender' => 'Male',
        'facility_id' => $facilityB->id,
    ]);

    return compact('tenant', 'facilityA', 'facilityB', 'user', 'role', 'patientAtA', 'patientAtB');
}

test('a user assigned only to one facility cannot see a patient registered at another facility', function () {
    $env = buildTwoFacilityTenant();

    $this->actingAs($env['user'])
        ->get(route('patients.show', $env['patientAtB']->id))
        ->assertNotFound();
});

test('a user assigned only to one facility can see a patient registered at their own facility', function () {
    $env = buildTwoFacilityTenant();

    $this->actingAs($env['user'])
        ->get(route('patients.show', $env['patientAtA']->id))
        ->assertOk();
});

test('a facility-scoped user can see a patient from another facility once seen via an encounter at their own facility', function () {
    $env = buildTwoFacilityTenant();

    app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $env['patientAtB']->id,
        'facility_id' => $env['facilityA']->id,
        'department_id' => null,
        'provider_id' => $env['user']->id,
        'encounter_type' => 'OPD',
    ]);

    $this->actingAs($env['user'])
        ->get(route('patients.show', $env['patientAtB']->id))
        ->assertOk();
});

test('a user with a global (facility-unscoped) role assignment sees every patient in the tenant', function () {
    $env = buildTwoFacilityTenant();
    app(AssignUserRoleAction::class)->execute($env['user']->id, $env['role']->id, null);

    $this->actingAs($env['user'])
        ->get(route('patients.show', $env['patientAtB']->id))
        ->assertOk();
});

test('break-glass grants temporary access to a patient outside the user\'s assigned facility', function () {
    $env = buildTwoFacilityTenant();
    $permission = Permission::firstOrCreate(
        ['slug' => 'clinical.break_glass'],
        ['name' => 'Break Glass Emergency Access', 'domain' => 'Clinical']
    );
    $env['role']->permissions()->syncWithoutDetaching([$permission->id]);

    $this->actingAs($env['user'])
        ->get(route('patients.show', $env['patientAtB']->id))
        ->assertNotFound();

    $this->actingAs($env['user'])
        ->post(route('clinical.break-glass.store'), [
            'patient_id' => $env['patientAtB']->id,
            'justification' => 'Trauma patient transferred emergently from Facility B, needs immediate chart review.',
        ])
        ->assertRedirect();

    $this->actingAs($env['user'])
        ->get(route('patients.show', $env['patientAtB']->id))
        ->assertOk();
});

test('without an active break-glass session, the out-of-facility patient stays hidden', function () {
    $env = buildTwoFacilityTenant();

    $this->actingAs($env['user'])
        ->get(route('patients.show', $env['patientAtB']->id))
        ->assertNotFound();
});
