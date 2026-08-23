<?php

namespace App\Domains\Audit\Listeners;

use App\Core\Context\TenantContext;
use App\Domains\Identity\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * TenantContextMiddleware resolves tenancy once, before the route handler
 * runs — which means for the login request itself, it runs before anyone
 * is authenticated and has no tenant to find (no subdomain in dev, no
 * X-Tenant-ID on a plain browser form post). The user's tenant is only
 * known the moment Auth::login() actually succeeds, inside the request
 * that authenticates them. Without this, anything else queried through a
 * tenant-scoped Eloquent model later in that same request runs with no
 * tenant context: the BelongsToTenant scope silently filters nothing.
 * (The LOGIN audit write itself no longer depends on this — AuditLogger
 * sets its own Postgres session tenant from the tenant_id it's given —
 * but this still matters for anything else in-request that isn't.)
 *
 * Registered ahead of LogSuccessfulLogin so the audit write it performs
 * lands with a real tenant context already established.
 */
class EstablishTenantContextOnLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $tenantId = $event->user->tenant_id;

        if (! $tenantId) {
            return;
        }

        app(TenantContext::class)->setTenantId($tenantId);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $tenantId]);
        }
    }
}
