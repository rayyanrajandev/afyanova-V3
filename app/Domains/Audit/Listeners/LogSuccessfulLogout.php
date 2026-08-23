<?php

namespace App\Domains\Audit\Listeners;

use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Identity\Models\User;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function __construct(
        protected AuditLogger $logger
    ) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->tenant_id) {
            return;
        }

        $this->logger->log([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'event_category' => 'AUTH',
            'action' => 'LOGOUT',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }
}
