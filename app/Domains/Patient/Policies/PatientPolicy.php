<?php

namespace App\Domains\Patient\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Patient\Models\Patient;

class PatientPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function view(User $user, Patient $patient): bool
    {
        return $this->auth->hasPermission($user, 'patient.registry.view');
    }

    public function create(User $user): bool
    {
        return $this->auth->hasPermission($user, 'patient.registry.create');
    }

    public function manageProblems(User $user, Patient $patient): bool
    {
        return $this->auth->hasPermission($user, 'clinical.problem-list.manage');
    }

    public function reconcileMedications(User $user, Patient $patient): bool
    {
        return $this->auth->hasPermission($user, 'pharmacy.medication-reconciliation.record');
    }
}
