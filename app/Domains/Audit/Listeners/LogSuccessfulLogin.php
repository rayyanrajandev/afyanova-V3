<?php

namespace App\Domains\Audit\Listeners;

use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Identity\Models\User;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function __construct(
        protected AuditLogger $logger
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        // $event->user is typed as the generic Authenticatable contract;
        // this app has exactly one implementation, so this both narrows
        // the type and guards against a hypothetical second guard.
        if (! $user instanceof User || ! $user->tenant_id) {
            return;
        }

        $this->logger->log([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'event_category' => 'AUTH',
            'action' => 'LOGIN',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }
}
