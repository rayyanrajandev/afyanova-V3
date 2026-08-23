<?php

namespace App\Domains\Procedure\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Procedure\Models\ProcedureOrder;

class ProcedureOrderPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function order(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'procedure.order.create', $facilityId);
    }

    public function execute(User $user, ProcedureOrder $order): bool
    {
        return $this->auth->hasPermission($user, 'procedure.order.execute', $order->encounter?->facility_id);
    }

    public function bookSurgery(User $user, ProcedureOrder $order): bool
    {
        return $this->auth->hasPermission($user, 'procedure.theatre.book', $order->encounter?->facility_id);
    }
}
