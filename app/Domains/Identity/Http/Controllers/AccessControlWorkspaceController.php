<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Identity\Actions\AssignUserRoleAction;
use App\Domains\Identity\Actions\UpdateRolePermissionsAction;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\RoleAssignment;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Tenancy\Models\Department;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AccessControlWorkspaceController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['identity.user.manage', 'identity.role.manage']);

        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'users' => 'identity.user.manage',
            'roles' => 'identity.role.manage',
            'permissions' => 'identity.role.manage',
            'assignRole' => 'identity.roles.assign',
            'updatePermissions' => 'identity.permissions.manage',
            'facilities' => 'identity.user.manage',
        ]);

        // No Tenant::first() fallback: this handler is only reachable
        // authenticated, and users.tenant_id is NOT NULL at the DB level —
        // the fallback could never fire from a real request, and silently
        // scoping this list to an arbitrary other tenant instead of failing
        // is a landmine, not a safety net.
        $tenantId = auth()->user()?->tenant_id;

        $roles = Role::with('permissions')
            ->where('tenant_id', $tenantId)
            ->get();

        if ($tenantId && $roles->count() < 10) {
            app(\App\Domains\Tenancy\Actions\SyncTenantStandardRolesAction::class)->execute($tenantId);
            $roles = Role::with('permissions')
                ->where('tenant_id', $tenantId)
                ->get();
        }

        $users = $can['users']
            ? User::with(['roleAssignments.role', 'roleAssignments.facility', 'roleAssignments.department', 'roles'])
                ->where('tenant_id', $tenantId)
                ->get()
            : collect();

        $permissions = Permission::all()->groupBy('domain');
        $facilities = Facility::with('departments')->where('tenant_id', $tenantId)->get();

        $selectedUserId = $can['users'] ? $request->get('user_id', $users->first()?->id) : null;
        $selectedUser = $can['users'] ? $users->firstWhere('id', $selectedUserId) : null;
        $effectivePermissions = ($can['users'] && $selectedUser) ? $authService->getUserPermissions($selectedUser) : [];

        return Inertia::render('Workspace/AccessControlWorkspace', [
            'can' => $can,
            'users' => $users,
            'roles' => $roles,
            'permissionsByDomain' => $permissions,
            'facilities' => $facilities,
            'selectedUserId' => $selectedUserId,
            'effectivePermissions' => $effectivePermissions,
            'metrics' => [
                'total_users' => $can['users'] ? $users->count() : null,
                'total_roles' => $roles->count(),
                'total_permissions' => $can['permissions'] ? Permission::count() : null,
                'multi_facility_assignments' => $can['users'] ? RoleAssignment::whereNotNull('facility_id')->count() : null,
                'total_facilities' => $facilities->count(),
            ],
        ]);
    }

    public function assignRole(Request $request, AssignUserRoleAction $action, AuthorizationService $authService): RedirectResponse
    {
        abort_unless(
            $authService->hasPermission($request->user(), 'identity.roles.assign'),
            403,
            'You are not permitted to assign roles.'
        );

        $validated = $request->validate([
            'user_id' => 'required|string',
            'role_id' => 'required|string',
            'facility_id' => 'nullable|string',
            'department_id' => 'nullable|string',
        ]);

        $action->execute(
            $validated['user_id'],
            $validated['role_id'],
            $validated['facility_id'] ?? null,
            $validated['department_id'] ?? null
        );

        return back()->with('success', 'Role assigned successfully.');
    }

    public function updatePermissions(Request $request, string $roleId, UpdateRolePermissionsAction $action, AuthorizationService $authService): RedirectResponse
    {
        abort_unless(
            $authService->hasPermission($request->user(), 'identity.permissions.manage'),
            403,
            'You are not permitted to manage role permissions.'
        );

        $validated = $request->validate([
            'permission_ids' => 'required|array',
        ]);

        $action->execute($roleId, $validated['permission_ids']);

        return back()->with('success', 'Role permissions updated successfully.');
    }

    public function testPermission(Request $request, AuthorizationService $authService): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|string',
            'permission_slug' => 'required|string',
            'facility_id' => 'nullable|string',
            'department_id' => 'nullable|string',
        ]);

        $user = User::withoutGlobalScopes()->findOrFail((string) $validated['user_id']);
        $hasAccess = $authService->hasPermission(
            $user,
            $validated['permission_slug'],
            $validated['facility_id'] ?? null,
            $validated['department_id'] ?? null
        );

        return response()->json([
            'user' => $user->first_name.' '.$user->last_name,
            'permission' => $validated['permission_slug'],
            'facility_id' => $validated['facility_id'],
            'granted' => $hasAccess,
        ]);
    }

    public function storeUser(Request $request, AuthorizationService $authService, AssignUserRoleAction $assignRoleAction): RedirectResponse
    {
        abort_unless($authService->hasPermission($request->user(), 'identity.user.manage') || $authService->isTenantAdmin($request->user()), 403);

        // No Tenant::first() fallback: this handler is only reachable
        // authenticated, and users.tenant_id is NOT NULL at the DB level —
        // the fallback could never fire from a real request. Worth being
        // strict about it here specifically: this value goes straight into
        // a new row's tenant_id below, so a silent fallback would create a
        // record owned by an arbitrary other tenant instead of failing.
        $tenantId = auth()->user()?->tenant_id;

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'professional_registration_no' => 'nullable|string|max:50',
            'role_id' => 'required|string|exists:roles,id',
            'facility_id' => 'nullable|string|exists:facilities,id',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'tenant_id' => $tenantId,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'professional_registration_no' => $validated['professional_registration_no'] ?? null,
            'password_hash' => Hash::make($validated['password']),
            'status' => 'Active',
            'two_factor_enabled' => false,
        ]);

        $assignRoleAction->execute($user->id, $validated['role_id'], $validated['facility_id'] ?? null);

        return back()->with('success', "Staff member {$user->name} created and credentialed successfully.");
    }

    public function updateUser(Request $request, User $user, AuthorizationService $authService): RedirectResponse
    {
        abort_unless($authService->hasPermission($request->user(), 'identity.user.manage') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'professional_registration_no' => 'nullable|string|max:50',
            'status' => 'required|in:Active,Suspended,Inactive,active,suspended,inactive',
        ]);

        $validated['status'] = ucfirst(strtolower($validated['status']));
        $user->update($validated);
        $authService->clearUserCache($user);

        return back()->with('success', "Staff profile for {$user->name} updated successfully.");
    }

    public function toggleUserStatus(Request $request, User $user, AuthorizationService $authService): RedirectResponse
    {
        abort_unless($authService->hasPermission($request->user(), 'identity.user.manage') || $authService->isTenantAdmin($request->user()), 403);

        $isCurrentlyActive = strtolower($user->status ?? 'active') === 'active';
        $newStatus = $isCurrentlyActive ? 'Suspended' : 'Active';
        $user->update(['status' => $newStatus]);
        $authService->clearUserCache($user);

        return back()->with('success', "User account {$user->name} status changed to {$newStatus}.");
    }

    public function resetPassword(Request $request, User $user, AuthorizationService $authService): RedirectResponse
    {
        abort_unless($authService->hasPermission($request->user(), 'identity.user.manage') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user->update([
            'password_hash' => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Password for {$user->name} has been reset successfully.");
    }

    public function storeFacility(Request $request, AuthorizationService $authService): RedirectResponse
    {
        abort_unless($authService->hasPermission($request->user(), 'identity.user.manage') || $authService->isTenantAdmin($request->user()), 403);

        // No Tenant::first() fallback: this handler is only reachable
        // authenticated, and users.tenant_id is NOT NULL at the DB level —
        // the fallback could never fire from a real request. Worth being
        // strict about it here specifically: this value goes straight into
        // a new row's tenant_id below, so a silent fallback would create a
        // record owned by an arbitrary other tenant instead of failing.
        $tenantId = auth()->user()?->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50',
            'facility_type' => 'required|string|max:50',
            'hfr_code' => 'nullable|string|max:50',
            'license_number' => 'nullable|string|max:50',
            'physical_address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:100',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['is_active'] = true;

        $facility = Facility::create($validated);

        return back()->with('success', "Facility branch {$facility->name} created successfully.");
    }

    public function updateFacility(Request $request, Facility $facility, AuthorizationService $authService): RedirectResponse
    {
        abort_unless($authService->hasPermission($request->user(), 'identity.user.manage') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50',
            'facility_type' => 'required|string|max:50',
            'hfr_code' => 'nullable|string|max:50',
            'license_number' => 'nullable|string|max:50',
            'physical_address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:100',
            'is_active' => 'required|boolean',
        ]);

        $facility->update($validated);

        return back()->with('success', "Facility branch {$facility->name} updated successfully.");
    }

    public function storeDepartment(Request $request, AuthorizationService $authService): RedirectResponse
    {
        abort_unless($authService->hasPermission($request->user(), 'identity.user.manage') || $authService->isTenantAdmin($request->user()), 403);

        // No Tenant::first() fallback: this handler is only reachable
        // authenticated, and users.tenant_id is NOT NULL at the DB level —
        // the fallback could never fire from a real request. Worth being
        // strict about it here specifically: this value goes straight into
        // a new row's tenant_id below, so a silent fallback would create a
        // record owned by an arbitrary other tenant instead of failing.
        $tenantId = auth()->user()?->tenant_id;

        $validated = $request->validate([
            'facility_id' => 'required|string|exists:facilities,id',
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50',
            'department_type' => 'required|string|max:50',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['is_active'] = true;

        Department::create($validated);

        return back()->with('success', "Department {$validated['name']} registered successfully.");
    }
}
