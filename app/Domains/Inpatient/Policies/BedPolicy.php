<?php

namespace App\Domains\Inpatient\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Inpatient\Models\Bed;

class BedPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function updateStatus(User $user, Bed $bed): bool
    {
        return $this->auth->hasPermission($user, 'inpatient.bed.manage', $bed->facility_id);
    }
}
