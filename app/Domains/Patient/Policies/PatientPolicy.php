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

    /**
     * Patient row-visibility (which patients this user's queries even see)
     * is already enforced by Patient::booted()'s facility global scope —
     * registered_at_facility_id, seen-via-encounter, a global role
     * assignment, or an active break-glass override. By the time a Patient
     * instance reaches here, that has already narrowed it to one of the
     * user's own assigned facilities (or a global grant). So this check is
     * just "does the user hold the permission somewhere" — global scope, or
     * any facility they're actually assigned to — not tied to one specific
     * facility_id on the patient the way EncounterPolicy::view() can be
     * (an Encounter has exactly one facility; a Patient does not).
     */
    public function view(User $user, Patient $patient): bool
    {
        if ($this->auth->hasPermission($user, 'patient.registry.view')) {
            return true;
        }

        return $user->roleAssignments()
            ->whereNotNull('facility_id')
            ->pluck('facility_id')
            ->unique()
            ->contains(fn ($facilityId) => $this->auth->hasPermission($user, 'patient.registry.view', $facilityId));
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

    public function recordAllergy(User $user, Patient $patient): bool
    {
        return $this->auth->hasPermission($user, 'clinical.allergy.record');
    }

    public function amendAllergy(User $user, Patient $patient): bool
    {
        return $this->auth->hasPermission($user, 'clinical.allergy.verify');
    }
}
