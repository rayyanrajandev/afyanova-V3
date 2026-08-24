<?php

namespace App\Domains\Clinical\Policies;

use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;

class EncounterPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function view(User $user, Encounter $encounter): bool
    {
        return $this->auth->hasPermission($user, 'clinical.encounter.view', $encounter->facility_id);
    }

    public function create(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'clinical.encounter.create', $facilityId);
    }

    public function update(User $user, Encounter $encounter): bool
    {
        if (! $this->auth->hasPermission($user, 'clinical.encounter.update', $encounter->facility_id)) {
            return false;
        }

        if ($encounter->status === 'Completed' || $encounter->status === 'Closed') {
            return false;
        }

        return $encounter->provider_id === $user->id
            || $this->auth->hasPermission($user, 'clinical.encounter.override', $encounter->facility_id);
    }

    public function signNotes(User $user, Encounter $encounter): bool
    {
        // Enforce medical officer permission boundary
        return $this->auth->hasPermission($user, 'clinical.notes.sign', $encounter->facility_id);
    }

    public function orderLabs(User $user, Encounter $encounter): bool
    {
        return $this->auth->hasPermission($user, 'lab.order.create', $encounter->facility_id);
    }

    public function recordConsent(User $user, Encounter $encounter): bool
    {
        return $this->auth->hasPermission($user, 'clinical.consent.record', $encounter->facility_id);
    }

    public function createReferral(User $user, Encounter $encounter): bool
    {
        return $this->auth->hasPermission($user, 'clinical.referral.create', $encounter->facility_id);
    }

    public function administerImmunization(User $user, Encounter $encounter): bool
    {
        return $this->auth->hasPermission($user, 'clinical.immunization.administer', $encounter->facility_id);
    }

    public function recordAncVisit(User $user, Encounter $encounter): bool
    {
        return $this->auth->hasPermission($user, 'clinical.anc.record', $encounter->facility_id);
    }

    public function recordPartograph(User $user, Encounter $encounter): bool
    {
        return $this->auth->hasPermission($user, 'clinical.partograph.record', $encounter->facility_id);
    }

    public function recordVitals(User $user, Encounter $encounter): bool
    {
        return $this->auth->hasPermission($user, 'clinical.vitals.record', $encounter->facility_id);
    }

    public function createNote(User $user, Encounter $encounter): bool
    {
        return $this->auth->hasPermission($user, 'clinical.notes.create', $encounter->facility_id);
    }
}
