<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Cache;

class AuthorizationService
{
    /**
     * Determine if the user has a specific permission globally, or scoped to a facility/department.
     */
    public function hasPermission(User $user, string $permissionSlug, ?string $facilityId = null, ?string $departmentId = null): bool
    {
        // Platform Superadmin has global platform access
        if ($this->isSuperAdmin($user)) {
            if (in_array($permissionSlug, ['clinical.notes.sign', 'pharmacy.dispense.execute', 'lab.result.verify'])) {
                return $this->evaluateAssignments($user, $permissionSlug, $facilityId, $departmentId);
            }

            return true;
        }

        // Tenant Admin has full tenant access except clinical safety violations
        if ($this->isTenantAdmin($user)) {
            if ($permissionSlug === 'platform.superadmin.access') {
                return false;
            }

            // Enforce clinical safety boundary: Admins cannot sign clinical notes or dispense medication without clinical credentials
            if (in_array($permissionSlug, ['clinical.notes.sign', 'pharmacy.dispense.execute', 'lab.result.verify'])) {
                // Must have explicit clinician/pharmacist role assignment
                return $this->evaluateAssignments($user, $permissionSlug, $facilityId, $departmentId);
            }

            return true;
        }

        return $this->evaluateAssignments($user, $permissionSlug, $facilityId, $departmentId);
    }

    /**
     * Whether the user holds a global-scope assignment to the 'super-admin' role.
     */
    public function isSuperAdmin(User $user): bool
    {
        $cacheKey = "user:{$user->id}:is-super-admin";

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            return $user->roleAssignments()
                ->withoutGlobalScopes()
                ->whereNull('facility_id')
                ->whereHas('role', fn ($query) => $query->withoutGlobalScopes()->whereIn('slug', ['super-admin', 'platform-admin']))
                ->exists();
        });
    }

    /**
     * Whether the user holds a global-scope assignment to the 'tenant-admin' role.
     */
    public function isTenantAdmin(User $user): bool
    {
        $cacheKey = "tenant:{$user->tenant_id}:user:{$user->id}:is-tenant-admin";

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            return $user->roleAssignments()
                ->withoutGlobalScopes()
                ->whereNull('facility_id')
                ->whereHas('role', fn ($query) => $query->withoutGlobalScopes()->where('slug', 'tenant-admin'))
                ->exists();
        });
    }

    /**
     * Get all active permissions for a user within a resolved scope.
     */
    public function getUserPermissions(User $user, ?string $facilityId = null, ?string $departmentId = null): array
    {
        $map = $this->getPermissionsMap($user);
        $permissions = [];

        if ($facilityId !== null) {
            // Specific facility scope
            $globalKey = $this->buildScopeKey(null, null);
            $facilityKey = $this->buildScopeKey($facilityId, null);
            $permissions = array_merge($permissions, $map[$globalKey] ?? [], $map[$facilityKey] ?? []);

            if ($departmentId !== null) {
                $deptKey = $this->buildScopeKey($facilityId, $departmentId);
                $permissions = array_merge($permissions, $map[$deptKey] ?? []);
            }
        } else {
            // Aggregate all permissions across all assigned scopes
            foreach ($map as $scopePerms) {
                $permissions = array_merge($permissions, $scopePerms);
            }
        }

        // If user is tenant-admin, they have all non-platform tenant permissions
        if ($this->isTenantAdmin($user)) {
            $allTenantPerms = \App\Domains\Identity\Models\Permission::where('domain', '!=', 'Platform')->pluck('slug')->toArray();
            $permissions = array_merge($permissions, $allTenantPerms);
        }

        // If user is super-admin, they have all permissions including platform control
        if ($this->isSuperAdmin($user)) {
            $allPerms = \App\Domains\Identity\Models\Permission::pluck('slug')->toArray();
            $permissions = array_merge($permissions, $allPerms);
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Clear permission cache for a user.
     */
    public function clearUserCache(User $user): void
    {
        Cache::forget("tenant:{$user->tenant_id}:user:{$user->id}:permissions");
        Cache::forget("tenant:{$user->tenant_id}:user:{$user->id}:is-tenant-admin");
        Cache::forget("user:{$user->id}:is-super-admin");
    }

    private function evaluateAssignments(User $user, string $permissionSlug, ?string $facilityId = null, ?string $departmentId = null): bool
    {
        $permissionsMap = $this->getPermissionsMap($user);

        // Check global scope (facility_id = null, department_id = null)
        $globalKey = $this->buildScopeKey(null, null);
        if (in_array($permissionSlug, $permissionsMap[$globalKey] ?? [])) {
            return true;
        }

        // Check facility scope
        if ($facilityId) {
            $facilityKey = $this->buildScopeKey($facilityId, null);
            if (in_array($permissionSlug, $permissionsMap[$facilityKey] ?? [])) {
                return true;
            }

            // Check department scope within facility
            if ($departmentId) {
                $deptKey = $this->buildScopeKey($facilityId, $departmentId);
                if (in_array($permissionSlug, $permissionsMap[$deptKey] ?? [])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getPermissionsMap(User $user): array
    {
        $cacheKey = "tenant:{$user->tenant_id}:user:{$user->id}:permissions";

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $assignments = $user->roleAssignments()->withoutGlobalScopes()->with(['role' => fn ($q) => $q->withoutGlobalScopes()->with('permissions')])->get();
            $map = [];

            foreach ($assignments as $assignment) {
                if ($assignment->role) {
                    foreach ($assignment->role->permissions as $permission) {
                        $scopeKey = $this->buildScopeKey($assignment->facility_id, $assignment->department_id);
                        $map[$scopeKey][] = $permission->slug;
                    }
                }
            }

            return $map;
        });
    }

    private function buildScopeKey(?string $facilityId, ?string $departmentId): string
    {
        return ($facilityId ?? 'GLOBAL').':'.($departmentId ?? 'GLOBAL');
    }
}
