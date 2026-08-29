<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\RoleAssignment;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use Illuminate\Support\Facades\DB;

class UnassignUserRoleAction
{
    public function __construct(
        protected AuthorizationService $authService
    ) {}

    public function execute(string $roleAssignmentId): bool
    {
        return DB::transaction(function () use ($roleAssignmentId) {
            $assignment = RoleAssignment::withoutGlobalScopes()->findOrFail($roleAssignmentId);
            $user = User::withoutGlobalScopes()->find($assignment->user_id);

            $deleted = $assignment->delete();

            if ($user) {
                $this->authService->clearUserCache($user);
            }

            return (bool) $deleted;
        });
    }
}
