<?php

namespace App\Domains\Inventory\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Inventory\Models\InventoryLocation;

class InventoryPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function viewLocation(User $user, InventoryLocation $location): bool
    {
        return $this->auth->hasPermission($user, 'inventory.location.view', $location->facility_id);
    }

    public function dispatchTransfer(User $user, InventoryLocation $sourceLocation): bool
    {
        return $this->auth->hasPermission($user, 'inventory.transfer.dispatch', $sourceLocation->facility_id);
    }

    public function confirmTransfer(User $user, InventoryLocation $destLocation): bool
    {
        return $this->auth->hasPermission($user, 'inventory.transfer.confirm', $destLocation->facility_id);
    }

    public function createPurchaseOrder(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'inventory.po.create', $facilityId);
    }

    public function approvePurchaseOrder(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'inventory.po.approve', $facilityId);
    }

    public function receiveGoods(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'inventory.grn.receive', $facilityId);
    }

    public function reconcileStocktake(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'inventory.stocktake.approve', $facilityId);
    }

    public function recordDda(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'inventory.dda.record', $facilityId);
    }
}
