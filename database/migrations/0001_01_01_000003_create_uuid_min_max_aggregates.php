<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PostgreSQL ships comparison operators for uuid (it has a full btree
     * opclass, so `ORDER BY`/`<`/`>` all work) but, unlike numeric/text/date
     * types, does not ship a MAX()/MIN() aggregate for it. Every model in
     * this app uses a UUID primary key, and Eloquent's ofMany()/
     * latestOfMany()/oldestOfMany() relations (used by
     * Patient::latestVital(), ProcedureOrder::latestExecution(),
     * RadiologyOrder::latestReport()) generate a MAX(id) tie-breaker
     * whenever eager-loaded across more than one parent row — which
     * throws "function max(uuid) does not exist" on Postgres specifically
     * (SQLite has no such restriction, which is why this was invisible to
     * the test suite). `greatest`/`least` are Postgres's own built-in
     * functions that work polymorphically on any type with comparison
     * operators, including uuid, making them the correct — and the
     * standard, documented — state-transition function for exactly this
     * gap.
     *
     * GREATEST()/LEAST() can't be used as an aggregate's sfunc directly —
     * they're parser special-forms in Postgres, not catalogued functions
     * with an OID — so each is wrapped in a trivial SQL function first.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // migrate:fresh drops tables, not functions/aggregates — these
        // survive across runs, so drop any prior copy first to keep this
        // migration safely re-runnable rather than failing with
        // "already exists" on a second migrate:fresh.
        DB::statement('DROP AGGREGATE IF EXISTS max(uuid)');
        DB::statement('DROP FUNCTION IF EXISTS uuid_greatest(uuid, uuid)');
        DB::statement('DROP AGGREGATE IF EXISTS min(uuid)');
        DB::statement('DROP FUNCTION IF EXISTS uuid_least(uuid, uuid)');

        DB::statement('CREATE FUNCTION uuid_greatest(uuid, uuid) RETURNS uuid AS $$ SELECT GREATEST($1, $2) $$ LANGUAGE SQL IMMUTABLE');
        DB::statement('CREATE AGGREGATE max(uuid) (sfunc = uuid_greatest, stype = uuid)');

        DB::statement('CREATE FUNCTION uuid_least(uuid, uuid) RETURNS uuid AS $$ SELECT LEAST($1, $2) $$ LANGUAGE SQL IMMUTABLE');
        DB::statement('CREATE AGGREGATE min(uuid) (sfunc = uuid_least, stype = uuid)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP AGGREGATE IF EXISTS max(uuid)');
        DB::statement('DROP FUNCTION IF EXISTS uuid_greatest(uuid, uuid)');
        DB::statement('DROP AGGREGATE IF EXISTS min(uuid)');
        DB::statement('DROP FUNCTION IF EXISTS uuid_least(uuid, uuid)');
    }
};
