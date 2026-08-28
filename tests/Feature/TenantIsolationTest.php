<?php

use App\Domains\Billing\Actions\GenerateInvoiceAction;
use App\Domains\Billing\Models\ChargeMasterItem;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Identity\Actions\AssignUserRoleAction;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A tenant, with a facility, an authenticated user, and one registered
 * patient — built by directly manipulating TenantContext (not HTTP), which
 * is fine for fixture setup since the assertions below exercise real HTTP
 * routes and let the actual middleware stack resolve tenancy.
 */
function buildIsolatedTenant(string $slug): array
{
    $tenant = Tenant::create([
        'name' => "Hospital {$slug}",
        'slug' => $slug,
        'domain' => "{$slug}.local",
        'status' => 'active',
    ]);

    setTestTenantContext($tenant->id);

    $facility = Facility::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Wing',
        'code' => 'MAIN-01',
        'is_active' => true,
    ]);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Doctor',
        'last_name' => ucfirst($slug),
        'email' => "doctor@{$slug}.local",
        'password_hash' => Hash::make('password123'),
        'status' => 'active',
    ]);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Patient',
        'last_name' => ucfirst($slug),
        'gender' => 'Female',
    ]);

    ChargeMasterItem::create([
        'tenant_id' => $tenant->id,
        'code' => 'CONSULT-OPD',
        'name' => 'General OPD Consultation',
        'category' => 'Consultation',
        'unit_price' => 20000.00,
        'effective_from' => now()->subYear()->toDateString(),
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'facility_id' => $facility->id,
        'department_id' => null,
        'provider_id' => $user->id,
        'encounter_type' => 'OPD',
    ]);

    $invoice = app(GenerateInvoiceAction::class)->execute($encounter);

    return compact('tenant', 'facility', 'user', 'patient', 'encounter', 'invoice');
}

test('a user cannot view another tenant\'s patient over HTTP', function () {
    $a = buildIsolatedTenant('alpha');
    $b = buildIsolatedTenant('beta');

    $this->actingAs($a['user'])
        ->get(route('patients.show', $b['patient']->id))
        ->assertNotFound();
});

test('a user cannot pay another tenant\'s invoice over HTTP', function () {
    $a = buildIsolatedTenant('gamma');
    $b = buildIsolatedTenant('delta');

    $this->actingAs($a['user'])
        ->post(route('billing.pay', $b['invoice']->id), ['amount' => 1000, 'payment_method' => 'Cash'])
        ->assertNotFound();
});

test('a user cannot list another tenant\'s facilities via the access-control workspace', function () {
    $a = buildIsolatedTenant('epsilon');
    buildIsolatedTenant('zeta');

    // buildIsolatedTenant('zeta') left the global TenantContext pointed at
    // zeta; AssignUserRoleAction looks up the user via a tenant-scoped
    // query, so it must be switched back to epsilon before granting a[user] a role.
    setTestTenantContext($a['tenant']->id);

    $role = Role::create(['tenant_id' => $a['tenant']->id, 'slug' => 'identity-admin', 'name' => 'Identity Admin']);
    $permission = Permission::firstOrCreate(
        ['slug' => 'identity.user.manage'],
        ['name' => 'Manage Staff Accounts', 'domain' => 'Identity']
    );
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    app(AssignUserRoleAction::class)->execute($a['user']->id, $role->id);

    $this->actingAs($a['user'])
        ->get(route('access-control.workspace'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('facilities', 1));
});

test('a user with no billing permission is forbidden from issuing a refund', function () {
    $a = buildIsolatedTenant('theta');

    $this->actingAs($a['user'])
        ->post(route('billing.refund', $a['invoice']->id), ['amount' => 1000, 'reason' => 'test'])
        ->assertForbidden();
});

test('a user with no identity permission cannot self-escalate via role assignment', function () {
    $a = buildIsolatedTenant('iota');

    $role = Role::create(['tenant_id' => $a['tenant']->id, 'slug' => 'tenant-admin', 'name' => 'Tenant Admin']);

    $this->actingAs($a['user'])
        ->post(route('access-control.roles.assign'), [
            'user_id' => $a['user']->id,
            'role_id' => $role->id,
        ])
        ->assertForbidden();
});

test('a user with the tenant-admin role assignment can assign roles', function () {
    $a = buildIsolatedTenant('kappa');

    $adminRole = Role::create(['tenant_id' => $a['tenant']->id, 'slug' => 'tenant-admin', 'name' => 'Tenant Admin']);
    app(AssignUserRoleAction::class)->execute($a['user']->id, $adminRole->id);

    $targetRole = Role::create(['tenant_id' => $a['tenant']->id, 'slug' => 'cashier', 'name' => 'Cashier']);

    $this->actingAs($a['user'])
        ->post(route('access-control.roles.assign'), [
            'user_id' => $a['user']->id,
            'role_id' => $targetRole->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});
