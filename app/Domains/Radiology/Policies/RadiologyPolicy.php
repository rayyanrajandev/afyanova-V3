<?php

namespace App\Domains\Radiology\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Radiology\Models\RadiologyOrder;

class RadiologyPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function view(User $user, RadiologyOrder $order): bool
    {
        return $this->auth->hasPermission($user, 'radiology.order.view', $order->facility_id);
    }

    public function create(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'radiology.order.create', $facilityId);
    }

    public function acquire(User $user, RadiologyOrder $order): bool
    {
        return $this->auth->hasPermission($user, 'radiology.study.acquire', $order->facility_id);
    }

    public function signReport(User $user, RadiologyOrder $order): bool
    {
        return $this->auth->hasPermission($user, 'radiology.report.sign', $order->facility_id);
    }

    public function amendReport(User $user, RadiologyOrder $order): bool
    {
        return $this->auth->hasPermission($user, 'radiology.report.amend', $order->facility_id);
    }
}
