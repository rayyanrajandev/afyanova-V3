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
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        ]);

        $tenantId = auth()->user()?->tenant_id ?? Tenant::first()?->id;

        $users = $can['users']
            ? User::with(['roleAssignments.role', 'roleAssignments.facility', 'roleAssignments.department'])
                ->where('tenant_id', $tenantId)
                ->get()
            : collect();

        $roles = ($can['roles'] || $can['permissions'])
            ? Role::with('permissions')
                ->where('tenant_id', $tenantId)
                ->get()
            : collect();

        $permissions = $can['permissions'] ? Permission::all()->groupBy('domain') : collect();
        $facilities = $can['users'] ? Facility::where('tenant_id', $tenantId)->get() : collect();

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
                'total_roles' => ($can['roles'] || $can['permissions']) ? $roles->count() : null,
                'total_permissions' => $can['permissions'] ? Permission::count() : null,
                'multi_facility_assignments' => $can['users'] ? RoleAssignment::whereNotNull('facility_id')->count() : null,
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

        $user = User::findOrFail((string) $validated['user_id']);
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
}
