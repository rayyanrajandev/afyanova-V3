<?php

namespace App\Domains\Audit\Listeners;

use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Identity\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;

class LogFailedLogin
{
    public function __construct(
        protected AuditLogger $logger
    ) {}

    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        if (! $email) {
            return;
        }

        // Pre-authentication: no tenant is known yet, so this lookup is
        // deliberately unscoped to find which tenant (if any) the attempted
        // email belongs to — the same structural exception users' RLS
        // policy documents for the login path itself.
        $user = User::where('email', $email)->first();

        if (! $user) {
            // No matching account — not attributable to any tenant's audit
            // trail. Still a signal worth keeping (enumeration/brute force
            // against a nonexistent account), just not a tenant-scoped one.
            Log::warning('Failed login attempt for unknown email', [
                'email' => $email,
                'ip' => request()->ip(),
            ]);

            return;
        }

        $this->logger->log([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'event_category' => 'SECURITY',
            'action' => 'LOGIN_FAILED',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }
}
