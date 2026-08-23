<?php

namespace App\Domains\Pharmacy\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Pharmacy\Models\Prescription;

class PrescriptionPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function view(User $user, Prescription $prescription): bool
    {
        return $this->auth->hasPermission($user, 'pharmacy.prescription.view', $prescription->encounter?->facility_id);
    }

    public function prescribe(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'pharmacy.prescription.create', $facilityId);
    }

    public function verify(User $user, Prescription $prescription): bool
    {
        return $this->auth->hasPermission($user, 'pharmacy.prescription.verify', $prescription->encounter?->facility_id);
    }

    public function dispense(User $user, Prescription $prescription): bool
    {
        return $this->auth->hasPermission($user, 'pharmacy.dispense.execute', $prescription->encounter?->facility_id);
    }
}
