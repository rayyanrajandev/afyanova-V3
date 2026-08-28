<?php

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

test('multi-tenant global scope isolates tenant records', function () {
    // Create Tenant A
    $tenantA = Tenant::create([
        'name' => 'Hospital Alpha',
        'slug' => 'hospital-alpha',
        'status' => 'active',
    ]);

    // Create Tenant B
    $tenantB = Tenant::create([
        'name' => 'Hospital Beta',
        'slug' => 'hospital-beta',
        'status' => 'active',
    ]);

    // Act as Tenant A
    setTestTenantContext($tenantA->id);
    $facilityA = Facility::create([
        'name' => 'Alpha Emergency',
        'code' => 'ALPHA-ER',
        'is_active' => true,
    ]);

    // Act as Tenant B
    setTestTenantContext($tenantB->id);
    $facilityB = Facility::create([
        'name' => 'Beta Surgery',
        'code' => 'BETA-SURG',
        'is_active' => true,
    ]);

    // Querying under Tenant B should ONLY return Beta
    $facilities = Facility::all();
    expect($facilities)->toHaveCount(1)
        ->and($facilities->first()->id)->toBe($facilityB->id);

    // Switching back to Tenant A should ONLY return Alpha
    setTestTenantContext($tenantA->id);
    $facilitiesA = Facility::all();
    expect($facilitiesA)->toHaveCount(1)
        ->and($facilitiesA->first()->id)->toBe($facilityA->id);
});

test('roles and permissions can be assigned to users', function () {
    $env = $this->setupTenantEnvironment();
    $tenant = $env['tenant'];
    $user = $env['user'];

    $perm = Permission::create([
        'name' => 'Create Prescription',
        'slug' => 'pharmacy.prescribe',
        'domain' => 'pharmacy',
    ]);

    $role = Role::create([
        'tenant_id' => $tenant->id,
        'name' => 'Pharmacist',
        'slug' => 'pharmacist',
    ]);

    $role->permissions()->attach($perm->id);

    DB::table('role_assignments')->insert([
        'id' => Uuid::uuid7()->toString(),
        'user_id' => $user->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->roles)->toHaveCount(2) // Doctor (from setup) + Pharmacist
        ->and($role->permissions)->toHaveCount(1);
});

test('audit logging captures cryptographic hashes for model events', function () {
    $env = $this->setupTenantEnvironment();
    $tenant = $env['tenant'];

    // Creating a facility triggers the Auditable trait
    $facility = Facility::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cardiology Unit',
        'code' => 'CARD-01',
        'is_active' => true,
    ]);

    $log = AuditLog::where('entity_id', $facility->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->tenant_id)->toBe($tenant->id)
        ->and($log->action)->toBe('CREATE')
        ->and($log->hash_signature)->not->toBeEmpty();
});

test('superadmin workspace renders global telemetry metrics without errors', function () {
    $env = $this->setupTenantEnvironment();
    $tenant = $env['tenant'];
    $user = $env['user'];

    $superRole = Role::create([
        'tenant_id' => $tenant->id,
        'name' => 'Platform Superadmin',
        'slug' => 'super-admin',
    ]);

    $perm = Permission::firstOrCreate([
        'name' => 'Superadmin Access',
        'slug' => 'platform.superadmin.access',
        'domain' => 'Platform',
    ]);

    $superRole->permissions()->attach($perm->id);

    DB::table('role_assignments')->insert([
        'id' => Uuid::uuid7()->toString(),
        'user_id' => $user->id,
        'role_id' => $superRole->id,
        'facility_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(\App\Domains\Identity\Services\AuthorizationService::class)->clearUserCache($user);

    $response = $this->actingAs($user)->get(route('superadmin.workspace'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Workspace/SuperadminWorkspace')
        ->has('telemetry')
        ->has('tenants')
        ->has('recentLogs')
        ->has('subscriptionPlans')
        ->has('masterCatalogs')
    );
});

test('superadmin can provision new hospital organization under RLS policies', function () {
    $action = app(\App\Domains\Tenancy\Actions\ProvisionTenantAction::class);

    $tenant = $action->execute([
        'name' => 'DSK Dispensary & Clinic',
        'slug' => 'dsk-dispensary',
        'subscription_tier' => 'starter',
        'subscription_status' => 'active',
        'main_facility_name' => 'Main DSK Wing',
        'facility_type' => 'Dispensary',
        'city' => 'Dar es Salaam',
        'region' => 'Dar es Salaam',
        'admin_first_name' => 'Juma',
        'admin_last_name' => 'Rashid',
        'admin_email' => 'admin@dskdispensary.co.tz',
        'admin_password' => 'Password123!',
    ]);

    expect($tenant)->toBeInstanceOf(Tenant::class)
        ->and($tenant->name)->toBe('DSK Dispensary & Clinic')
        ->and($tenant->slug)->toBe('dsk-dispensary');

    $facility = Facility::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
    expect($facility)->not->toBeNull()
        ->and($facility->name)->toBe('Main DSK Wing');

    // Verify initial admin user has tenant-admin role assigned
    $adminUser = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('email', 'admin@dskdispensary.co.tz')->first();
    expect($adminUser)->not->toBeNull();

    $authService = app(\App\Domains\Identity\Services\AuthorizationService::class);
    expect($authService->isTenantAdmin($adminUser))->toBeTrue()
        ->and($authService->isSuperAdmin($adminUser))->toBeFalse()
        ->and($authService->hasPermission($adminUser, 'platform.superadmin.access'))->toBeFalse()
        ->and($authService->hasPermission($adminUser, 'billing.invoice.view'))->toBeTrue()
        ->and($authService->hasPermission($adminUser, 'patient.registry.view'))->toBeTrue();

    // Verify tenant admin lands on HospitalExecutiveWorkspace
    $dashboardResponse = $this->actingAs($adminUser)->get(route('dashboard'));
    $dashboardResponse->assertOk()
        ->assertInertia(fn ($page) => $page->component('Workspace/HospitalExecutiveWorkspace'));

    $superadminResponse = $this->actingAs($adminUser)->get(route('superadmin.workspace'));
    $superadminResponse->assertForbidden();
});

test('role-based landing routes tenant-admin to Executive Workspace and staff to Front-Desk Hub', function () {
    $env = $this->setupTenantEnvironment();
    $tenant = $env['tenant'];

    // 1. Staff user (Doctor/Receptionist) lands on Front-Desk HomeWorkspace
    $staffUser = User::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Staff',
        'last_name' => 'Reception',
        'email' => 'reception_unique@afyanova.local',
        'password_hash' => \Illuminate\Support\Facades\Hash::make('Password123!'),
        'status' => 'active',
    ]);
    $receptionRole = Role::firstOrCreate(
        ['tenant_id' => $tenant->id, 'slug' => 'receptionist'],
        ['name' => 'Receptionist']
    );
    $schedPerm = Permission::firstOrCreate(['slug' => 'scheduling.queue.view'], ['name' => 'View Queue', 'domain' => 'Scheduling']);
    $receptionRole->permissions()->syncWithoutDetaching([$schedPerm->id]);
    \App\Domains\Identity\Models\RoleAssignment::create([
        'user_id' => $staffUser->id,
        'role_id' => $receptionRole->id,
        'facility_id' => null,
    ]);

    $response = $this->actingAs($staffUser)->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('Workspace/HomeWorkspace'));

    // 2. Tenant Admin lands on Executive Workspace
    $adminUser = User::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Hospital',
        'last_name' => 'Director',
        'email' => 'director_unique@afyanova.local',
        'password_hash' => \Illuminate\Support\Facades\Hash::make('Password123!'),
        'status' => 'active',
    ]);
    $adminRole = Role::firstOrCreate(
        ['tenant_id' => $tenant->id, 'slug' => 'tenant-admin'],
        ['name' => 'Hospital Admin']
    );
    \App\Domains\Identity\Models\RoleAssignment::create([
        'user_id' => $adminUser->id,
        'role_id' => $adminRole->id,
        'facility_id' => null,
    ]);

    $adminResponse = $this->actingAs($adminUser)->get(route('dashboard'));
    $adminResponse->assertOk()
        ->assertInertia(fn ($page) => $page->component('Workspace/HospitalExecutiveWorkspace'));
});

test('superadmin can add facility branch to existing tenant within quota', function () {
    $env = $this->setupTenantEnvironment();
    $tenant = $env['tenant'];
    $user = $env['user'];

    $superRole = Role::create([
        'tenant_id' => $tenant->id,
        'name' => 'Platform Superadmin',
        'slug' => 'super-admin',
    ]);
    $perm = Permission::firstOrCreate([
        'name' => 'Superadmin Access',
        'slug' => 'platform.superadmin.access',
        'domain' => 'Platform',
    ]);
    $superRole->permissions()->attach($perm->id);
    DB::table('role_assignments')->insert([
        'id' => Uuid::uuid7()->toString(),
        'user_id' => $user->id,
        'role_id' => $superRole->id,
        'facility_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(\App\Domains\Identity\Services\AuthorizationService::class)->clearUserCache($user);

    $response = $this->actingAs($user)->post(route('superadmin.tenants.facilities.store', $tenant->id), [
        'name' => 'Mikocheni Health Center',
        'code' => 'MIK-01',
        'facility_type' => 'Health Center',
        'city' => 'Dar es Salaam',
        'region' => 'Dar es Salaam',
    ]);

    $response->assertRedirect();
    $branch = Facility::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', 'MIK-01')->first();
    expect($branch)->not->toBeNull()
        ->and($branch->name)->toBe('Mikocheni Health Center');
});

