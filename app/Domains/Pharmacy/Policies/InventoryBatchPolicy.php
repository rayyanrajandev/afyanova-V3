<?php

namespace App\Domains\Pharmacy\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Pharmacy\Models\InventoryBatch;

class InventoryBatchPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function receive(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'pharmacy.inventory.receive', $facilityId);
    }

    public function adjust(User $user, InventoryBatch $batch): bool
    {
        return $this->auth->hasPermission($user, 'pharmacy.inventory.adjust', $batch->facility_id);
    }
}
