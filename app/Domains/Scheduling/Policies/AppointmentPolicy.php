<?php

namespace App\Domains\Scheduling\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Scheduling\Models\Appointment;

class AppointmentPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function create(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'scheduling.appointment.create', $facilityId);
    }

    public function checkIn(User $user, Appointment $appointment): bool
    {
        return $this->auth->hasPermission($user, 'scheduling.appointment.checkin', $appointment->facility_id);
    }
}
