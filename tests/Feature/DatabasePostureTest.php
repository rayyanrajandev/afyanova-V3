<?php

use Illuminate\Support\Facades\DB;

/**
 * Row-level security is only a real second line of defense if the role the
 * application actually connects as is bound by it. Two Postgres role
 * attributes silently defeat every RLS policy on every table regardless of
 * FORCE ROW LEVEL SECURITY: SUPERUSER, and the separate, easy-to-miss
 * BYPASSRLS attribute (a role can be BYPASSRLS without being SUPERUSER at
 * all — checking only rolsuper, which is the more familiar attribute,
 * misses it entirely).
 *
 * This was found live during a security audit: the application's own
 * connection role had BYPASSRLS granted, and a query with an explicitly
 * wrong app.current_tenant_id session value still returned every tenant's
 * rows on a FORCE-protected table — RLS was doing nothing. This test turns
 * that discovery into a permanent guard: it fails loudly the moment the
 * connecting role regains either attribute, in dev or CI, rather than
 * relying on someone re-discovering it by hand.
 *
 * SQLite has no RLS/role concept, so this only asserts anything meaningful
 * against a real pgsql connection — the same guard pattern already used
 * throughout this codebase's own RLS migrations.
 */
test('the application database connection cannot bypass row-level security', function () {
    $role = DB::selectOne(
        'SELECT rolname, rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
    );

    expect($role)->not->toBeNull();

    expect($role->rolsuper)
        ->toBeFalsy("Database role '{$role->rolname}' is a Postgres SUPERUSER — row-level security is silently bypassed on every table for this connection, regardless of FORCE ROW LEVEL SECURITY. The application's runtime connection must use a non-superuser role.");

    expect($role->rolbypassrls)
        ->toBeFalsy("Database role '{$role->rolname}' has BYPASSRLS granted — row-level security is silently bypassed on every table for this connection, regardless of FORCE ROW LEVEL SECURITY or table ownership. Run: ALTER ROLE {$role->rolname} NOBYPASSRLS;");
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'Row-level security bypass only applies to the pgsql driver.');

/**
 * Every table that carries a tenant_id column enables and forces RLS,
 * except `users`, which is a deliberate, documented exception — see the
 * comment in its creation migration: authentication has to look a user up
 * by email before any tenant is known, so the app's own (table-owning)
 * role is intentionally left unforced there specifically, with every other
 * access path to `users` still going through BelongsToTenant's Eloquent
 * scope. This test locks that exception down to exactly one table, rather
 * than letting a future migration silently add another one.
 */
test('every tenant-scoped table forces row-level security, except the one documented exception', function () {
    $tables = DB::select(<<<'SQL'
        SELECT c.relname
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        JOIN information_schema.columns col
            ON col.table_schema = n.nspname AND col.table_name = c.relname
        WHERE n.nspname = 'public'
            AND c.relkind = 'r'
            AND col.column_name = 'tenant_id'
            AND c.relrowsecurity = true
            AND c.relforcerowsecurity = false
    SQL);

    $unforced = collect($tables)->pluck('relname')->all();

    expect($unforced)->toBe(['users']);
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'Row-level security only applies to the pgsql driver.');
