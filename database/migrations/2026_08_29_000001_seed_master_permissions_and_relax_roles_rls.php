<?php

use App\Domains\Tenancy\Actions\SyncTenantStandardRolesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remove FORCE ROW LEVEL SECURITY on roles table so the application
        // connection can reliably resolve authorization and role hierarchies
        // without false negative empty results when evaluating user permissions.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE roles NO FORCE ROW LEVEL SECURITY');
        }

        // 2. Ensure master system permissions and standard role bindings
        // exist in production database
        if (! app()->environment('testing')) {
            SyncTenantStandardRolesAction::ensureMasterPermissions();
            app(SyncTenantStandardRolesAction::class)->execute();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE roles FORCE ROW LEVEL SECURITY');
        }
    }
};
