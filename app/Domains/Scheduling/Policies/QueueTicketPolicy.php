<?php

namespace App\Domains\Scheduling\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Scheduling\Models\QueueTicket;

class QueueTicketPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function transfer(User $user, QueueTicket $ticket): bool
    {
        return $this->auth->hasPermission($user, 'scheduling.queue.transfer', $ticket->facility_id);
    }

    public function call(User $user, QueueTicket $ticket): bool
    {
        return $this->auth->hasPermission($user, 'scheduling.queue.call', $ticket->facility_id);
    }
}
