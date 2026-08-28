<?php

use App\Core\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Invariants');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Sets the acting tenant for a test fixture built by directly calling
 * Eloquent factories/Actions rather than going through a real HTTP
 * request — TenantContextMiddleware never runs for that code path, so
 * without this, only the PHP-level TenantContext object (which
 * BelongsToTenant's global scope and auto-fill read) gets set; the
 * Postgres session variable RLS policies check
 * (app.current_tenant_id, via set_config — see TenantContextMiddleware
 * and EstablishTenantContextOnLogin, the two places that set it for a
 * real request) never does. That was invisible for as long as the
 * application's database role had BYPASSRLS granted — every INSERT
 * succeeded regardless, because RLS was never actually being evaluated.
 * The moment that was fixed, every test fixture using the bare
 * `app(TenantContext::class)->setTenantId(...)` call started failing
 * with "new row violates row-level security policy," because the
 * fixture's own writes no longer satisfied a WITH CHECK clause that had
 * never really been checked before. Use this everywhere a test needs to
 * act as a given tenant outside of an actual HTTP round-trip.
 */
function setTestTenantContext(string $tenantId): void
{
    app(TenantContext::class)->setTenantId($tenantId);

    if (DB::getDriverName() === 'pgsql') {
        DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $tenantId]);
    }
}

function something()
{
    // ..
}
