<?php

namespace App\Domains\Procedure\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Procedure\Models\SurgicalBooking;
use App\Domains\Procedure\Models\WhoSurgicalChecklist;

class SurgicalBookingPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function saveChecklist(User $user, WhoSurgicalChecklist $checklist): bool
    {
        return $this->auth->hasPermission($user, 'procedure.theatre.checklist', $checklist->booking?->order?->encounter?->facility_id);
    }

    public function recordPacu(User $user, SurgicalBooking $booking): bool
    {
        return $this->auth->hasPermission($user, 'procedure.theatre.pacu', $booking->order?->encounter?->facility_id);
    }
}
