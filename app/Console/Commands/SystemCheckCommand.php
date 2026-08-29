<?php

namespace App\Console\Commands;

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Actions\SyncTenantStandardRolesAction;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemCheckCommand extends Command
{
    protected $signature = 'afya:doctor
        {--repair : Automatically repair missing permissions or unassigned tenant roles}';

    protected $description = 'Comprehensive enterprise diagnostic health check for PostgreSQL RLS, RBAC permissions, and tenancy integrity.';

    public function handle(): int
    {
        $this->newLine();
        $this->info('===============================================================');
        $this->info('           AFYANOVA ENTERPRISE SYSTEM DIAGNOSTICS              ');
        $this->info('===============================================================');
        $this->newLine();

        $allOk = true;

        // 1. Database & Driver
        $driver = DB::getDriverName();
        $this->line("• Database Driver: <comment>{$driver}</comment>");
        if ($driver === 'pgsql') {
            $rlsSetting = DB::scalar("SELECT current_setting('app.current_tenant_id', true)") ?: 'Not Set (Global Session)';
            $this->line("  Postgres RLS Session Tenant: <info>{$rlsSetting}</info>");
        }

        // 2. Permissions Table Integrity
        $permCount = Permission::count();
        $expectedPerms = count(SyncTenantStandardRolesAction::$masterPermissions);
        if ($permCount >= $expectedPerms) {
            $this->line("• System Permissions: <info>✓ {$permCount}/{$expectedPerms} Active</info>");
        } else {
            $allOk = false;
            $this->line("• System Permissions: <error>✗ {$permCount}/{$expectedPerms} Found (Incomplete)</error>");
            if ($this->option('repair')) {
                SyncTenantStandardRolesAction::ensureMasterPermissions();
                $this->info("  [Repair] Seeded all {$expectedPerms} master permissions.");
            } else {
                $this->comment("  Tip: Run with --repair to automatically seed all system permissions.");
            }
        }

        // 3. Tenants & Facilities
        $tenantsCount = Tenant::count();
        $facilitiesCount = Facility::withoutGlobalScopes()->count();
        $this->line("• Hospital Organizations (Tenants): <info>{$tenantsCount}</info>");
        $this->line("• Facility Branches (Total): <info>{$facilitiesCount}</info>");

        // 4. Role Bindings per Tenant
        $tenants = Tenant::all();
        $orphanTenants = 0;
        foreach ($tenants as $t) {
            $rolesCount = Role::withoutGlobalScopes()->where('tenant_id', $t->id)->count();
            if ($rolesCount < 10) {
                $orphanTenants++;
            }
        }

        if ($orphanTenants === 0) {
            $this->line("• Tenant Role Matrices: <info>✓ All {$tenantsCount} tenants have full standard roles</info>");
        } else {
            $this->line("• Tenant Role Matrices: <comment>! {$orphanTenants} tenants missing standard role blueprints</comment>");
            if ($this->option('repair')) {
                $synced = app(SyncTenantStandardRolesAction::class)->execute();
                $this->info("  [Repair] Synced {$synced} roles across all tenant organizations.");
            } else {
                $this->comment("  Tip: Run with --repair to sync standard roles to all tenants.");
            }
        }

        // 5. User & Admin Counts
        $totalUsers = User::withoutGlobalScopes()->count();
        $activeUsers = User::withoutGlobalScopes()->active()->count();
        $this->line("• Total Staff Accounts: <info>{$totalUsers}</info> (<info>{$activeUsers} Active</info>)");

        // 6. Cache Check
        try {
            Cache::put('afya_doctor_ping', 'pong', 10);
            $cacheVal = Cache::get('afya_doctor_ping');
            if ($cacheVal === 'pong') {
                $this->line("• Cache Subsystem (" . config('cache.default') . "): <info>✓ Operational</info>");
            } else {
                $this->line("• Cache Subsystem: <error>✗ Read/Write mismatch</error>");
                $allOk = false;
            }
        } catch (\Throwable $e) {
            $this->line("• Cache Subsystem: <error>✗ " . $e->getMessage() . "</error>");
            $allOk = false;
        }

        $this->newLine();
        if ($allOk) {
            $this->info("✓ All enterprise diagnostic checks passed successfully.");
        } else {
            $this->warn("! Some diagnostic checks require attention. Run `php artisan afya:doctor --repair` to fix automatically.");
        }
        $this->newLine();

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
