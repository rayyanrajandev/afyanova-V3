<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\RoleAssignment;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use Illuminate\Support\Facades\DB;

class AssignUserRoleAction
{
    public function __construct(
        protected AuthorizationService $authService
    ) {}

    public function execute(
        string $userId,
        string $roleId,
        ?string $facilityId = null,
        ?string $departmentId = null
    ): RoleAssignment {
        return DB::transaction(function () use ($userId, $roleId, $facilityId, $departmentId) {
            $user = User::findOrFail($userId);
            $role = Role::findOrFail($roleId);

            $assignment = RoleAssignment::firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'facility_id' => $facilityId,
                'department_id' => $departmentId,
            ]);

            $this->authService->clearUserCache($user);

            return $assignment->load(['role', 'facility', 'department']);
        });
    }
}
