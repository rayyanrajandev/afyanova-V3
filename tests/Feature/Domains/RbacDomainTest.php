<?php

use App\Core\Context\TenantContext;
use App\Domains\Identity\Actions\AssignUserRoleAction;
use App\Domains\Identity\Actions\UpdateRolePermissionsAction;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Afya Hospital',
        'slug' => 'afya-hospital',
        'domain' => 'afya.local',
        'status' => 'Active',
    ]);
    app(TenantContext::class)->setTenantId($this->tenant->id);

    $this->facilityA = Facility::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'FAC-DAR',
        'name' => 'Dar es Salaam Branch',
        'is_active' => true,
    ]);

    $this->facilityB = Facility::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'FAC-ARU',
        'name' => 'Arusha Branch',
        'is_active' => true,
    ]);

    $this->authService = app(AuthorizationService::class);
});

test('evaluates permissions accurately across facility scopes', function () {
    $doctorUser = User::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'scoped.doctor@afya.local',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'password_hash' => bcrypt('password123'),
        'role' => 'Doctor',
    ]);

    $doctorRole = Role::create([
        'tenant_id' => $this->tenant->id,
        'slug' => 'doctor',
        'name' => 'Medical Officer',
    ]);

    $perm = Permission::create([
        'slug' => 'clinical.encounter.create',
        'name' => 'Create Encounter',
        'domain' => 'Clinical',
    ]);

    $doctorRole->permissions()->sync([$perm->id]);

    // Assign doctor role scoped strictly to Facility A (Dar es Salaam)
    $assignAction = app(AssignUserRoleAction::class);
    $assignAction->execute($doctorUser->id, $doctorRole->id, $this->facilityA->id);

    // 1. Should have permission in Facility A
    $canInFacilityA = $this->authService->hasPermission($doctorUser, 'clinical.encounter.create', $this->facilityA->id);
    expect($canInFacilityA)->toBeTrue();

    // 2. Should NOT have permission in Facility B (Arusha)
    $canInFacilityB = $this->authService->hasPermission($doctorUser, 'clinical.encounter.create', $this->facilityB->id);
    expect($canInFacilityB)->toBeFalse();
});

test('enforces clinical safety boundary: non-clinicians cannot sign clinical notes', function () {
    $adminUser = User::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'system.admin@afya.local',
        'first_name' => 'System',
        'last_name' => 'Admin',
        'password_hash' => bcrypt('password123'),
        'role' => 'TenantAdmin',
    ]);

    $doctorUser = User::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'clinical.officer@afya.local',
        'first_name' => 'Clinician',
        'last_name' => 'Massawe',
        'password_hash' => bcrypt('password123'),
        'role' => 'Doctor',
    ]);

    $doctorRole = Role::create([
        'tenant_id' => $this->tenant->id,
        'slug' => 'doctor',
        'name' => 'Medical Officer',
    ]);

    $signPerm = Permission::create([
        'slug' => 'clinical.notes.sign',
        'name' => 'Sign Clinical Notes',
        'domain' => 'Clinical',
    ]);

    $doctorRole->permissions()->sync([$signPerm->id]);

    $assignAction = app(AssignUserRoleAction::class);
    $assignAction->execute($doctorUser->id, $doctorRole->id, $this->facilityA->id);

    // Doctor with role assignment CAN sign notes
    expect($this->authService->hasPermission($doctorUser, 'clinical.notes.sign', $this->facilityA->id))->toBeTrue();

    // Admin without clinical role assignment CANNOT sign notes (Safety Rule)
    expect($this->authService->hasPermission($adminUser, 'clinical.notes.sign', $this->facilityA->id))->toBeFalse();
});

test('invalidates cache dynamically when role permissions are modified', function () {
    $cashierUser = User::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'cashier.test@afya.local',
        'first_name' => 'Cashier',
        'last_name' => 'Test',
        'password_hash' => bcrypt('password123'),
        'role' => 'Cashier',
    ]);

    $cashierRole = Role::create([
        'tenant_id' => $this->tenant->id,
        'slug' => 'cashier',
        'name' => 'Cashier Role',
    ]);

    $payPerm = Permission::create([
        'slug' => 'billing.payment.collect',
        'name' => 'Collect Payment',
        'domain' => 'Billing',
    ]);

    $discountPerm = Permission::create([
        'slug' => 'billing.discount.approve',
        'name' => 'Approve Discount',
        'domain' => 'Billing',
    ]);

    $cashierRole->permissions()->sync([$payPerm->id]);

    $assignAction = app(AssignUserRoleAction::class);
    $assignAction->execute($cashierUser->id, $cashierRole->id);

    // Initially can collect payment, but cannot approve discount
    expect($this->authService->hasPermission($cashierUser, 'billing.payment.collect'))->toBeTrue();
    expect($this->authService->hasPermission($cashierUser, 'billing.discount.approve'))->toBeFalse();

    // Update role permissions to include discount approval
    $updateAction = app(UpdateRolePermissionsAction::class);
    $updateAction->execute($cashierRole->id, [$payPerm->id, $discountPerm->id]);

    // Permissions map should be updated immediately without stale cache
    expect($this->authService->hasPermission($cashierUser, 'billing.discount.approve'))->toBeTrue();
});
