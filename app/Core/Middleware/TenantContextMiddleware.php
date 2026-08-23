<?php

namespace App\Core\Middleware;

use App\Core\Context\TenantContext;
use App\Domains\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TenantContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var TenantContext $context */
        $context = App::make(TenantContext::class);

        // 1. An authenticated session's own tenant always wins — never let a
        //    client-supplied header override the tenant a logged-in user
        //    actually belongs to.
        $tenantId = $request->user()?->tenant_id;

        // 2. Unauthenticated flows (e.g. a signed API token, or a subdomain
        //    resolving before login) may still declare a tenant explicitly.
        if (! $tenantId) {
            $tenantId = $request->header('X-Tenant-ID');
        }

        if (! $tenantId && $request->getHost()) {
            $parts = explode('.', $request->getHost());
            if (count($parts) >= 3) {
                $slug = $parts[0];
                $tenant = Tenant::where('slug', $slug)->first();
                if ($tenant) {
                    $tenantId = $tenant->id;
                }
            }
        }

        if ($tenantId) {
            Log::info('TenantContextMiddleware matched tenant: '.$tenantId);
            $context->setTenantId($tenantId);

            // Activate PostgreSQL Row-Level Security for this connection.
            // Two things matter here:
            // 1. `set_config()`, not a raw `SET ... = ?` statement — `SET`
            //    does not accept bound parameters in Postgres at all (it
            //    errors), so the value has to go through the equivalent
            //    function call instead.
            // 2. The third argument is `is_local => false` (session-scoped,
            //    not `SET LOCAL`/transaction-scoped): most read queries
            //    (index/show actions) never open an explicit transaction,
            //    and a transaction-local setting is a no-op outside one —
            //    RLS policies would then see a NULL app.current_tenant_id
            //    and reject every row. Laravel does not pool PDO
            //    connections across requests by default, so a
            //    session-scoped value here does not leak between
            //    unrelated requests.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $tenantId]);
            }
        } else {
            Log::warning('TenantContextMiddleware could not find tenant. User is: '.($request->user() ? 'Authenticated' : 'Not Authenticated'));
        }

        return $next($request);
    }
}
