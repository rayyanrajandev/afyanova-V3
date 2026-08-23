<?php

namespace App\Http\Middleware;

use App\Core\Context\TenantContext;
use App\Domains\Audit\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side session idle-timeout middleware.
 *
 * Tracks the timestamp of the user's last authenticated request in the
 * session under the key `last_activity_at`. On every authenticated
 * request it compares now() against that timestamp. If the gap exceeds
 * SESSION_IDLE_TIMEOUT minutes (default 30 — appropriate for shared
 * clinical workstations), it:
 *
 *   1. Writes an IDLE_TIMEOUT audit entry on the hash-chained trail.
 *   2. Logs the user out and invalidates the session.
 *   3. Redirects to /login with ?reason=idle_timeout so the login page
 *      can surface a clear, non-alarming message.
 *
 * On every passing request it refreshes the timestamp.
 *
 * WHY NOT session.lifetime?
 * session.lifetime is the absolute TTL of the session cookie/record.
 * It does NOT measure inactivity — a user who last clicked 29 minutes
 * ago but whose session was created 90 minutes ago would still be live.
 * This middleware measures the gap since the last *request*, which is
 * the correct clinical-workstation definition of "idle".
 *
 * ORDERING:
 * This middleware runs after TenantContextMiddleware so that the audit
 * write has a tenant context already established.
 */
class SessionIdleTimeout
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Only applies to authenticated users.
        if (! Auth::check()) {
            return $next($request);
        }

        $timeoutMinutes = (int) config('session.idle_timeout', 30);
        $lastActivity = $request->session()->get('last_activity_at');

        if ($lastActivity && (time() - $lastActivity) > ($timeoutMinutes * 60)) {
            $this->expireSession($request);

            return redirect()->route('login', ['reason' => 'idle_timeout'])->with(
                'status',
                'Your session expired due to inactivity. Please sign in again.'
            );
        }

        // Refresh the activity clock on every passing request.
        $request->session()->put('last_activity_at', time());

        return $next($request);
    }

    private function expireSession(Request $request): void
    {
        $user = Auth::user();
        $tenantId = $user?->tenant_id ?? $this->tenantContext->getTenantId();

        // Write audit entry before logout so user context is still available.
        if ($user && $tenantId) {
            $this->audit->log([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'event_category' => 'SECURITY',
                'action' => 'IDLE_TIMEOUT',
                'entity_type' => 'User',
                'entity_id' => $user->id,
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
