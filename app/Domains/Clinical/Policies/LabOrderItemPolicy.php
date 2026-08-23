<?php

namespace App\Domains\Clinical\Policies;

use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;

class LabOrderItemPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function collect(User $user, LabOrderItem $item): bool
    {
        return $this->auth->hasPermission($user, 'lab.specimen.collect', $item->labOrder?->encounter?->facility_id);
    }

    public function recordResults(User $user, LabOrderItem $item): bool
    {
        return $this->auth->hasPermission($user, 'lab.result.record', $item->labOrder?->encounter?->facility_id);
    }

    public function verify(User $user, LabOrderItem $item): bool
    {
        return $this->auth->hasPermission($user, 'lab.result.verify', $item->labOrder?->encounter?->facility_id);
    }
}
