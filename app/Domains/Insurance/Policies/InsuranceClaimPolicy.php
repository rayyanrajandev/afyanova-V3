<?php

namespace App\Domains\Insurance\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Insurance\Models\InsuranceClaim;

class InsuranceClaimPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function adjudicate(User $user, InsuranceClaim $claim): bool
    {
        return $this->auth->hasPermission($user, 'insurance.claim.adjudicate', $claim->encounter?->facility_id);
    }
}
