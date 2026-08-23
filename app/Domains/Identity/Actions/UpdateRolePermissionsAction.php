<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use Illuminate\Support\Facades\DB;

class UpdateRolePermissionsAction
{
    public function __construct(
        protected AuthorizationService $authService
    ) {}

    public function execute(string $roleId, array $permissionIds): Role
    {
        return DB::transaction(function () use ($roleId, $permissionIds) {
            $role = Role::findOrFail($roleId);

            // Sync permissions
            $role->permissions()->sync($permissionIds);

            // Invalidate cache for all users holding this role
            $users = User::whereHas('roleAssignments', fn ($q) => $q->where('role_id', $roleId))->get();
            foreach ($users as $u) {
                $this->authService->clearUserCache($u);
            }

            return $role->load('permissions');
        });
    }
}
