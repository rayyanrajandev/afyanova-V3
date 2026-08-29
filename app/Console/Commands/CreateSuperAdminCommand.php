<?php

namespace App\Console\Commands;

use App\Core\Context\TenantContext;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\RoleAssignment;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bootstraps a single platform-superadmin account, deliberately independent
 * of DatabaseSeeder — that seeder refuses to run in production (see its own
 * guard) and, more to the point, mixes foundational reference data
 * (permissions, roles) with fake demo patients/staff in one gated method.
 * There was previously no way to get an initial admin account onto a fresh
 * production-environment database at all. This command is the minimal
 * alternative: creates (or reuses) exactly one tenant, one user, and one
 * globally-scoped 'super-admin' role assignment — nothing demo-flavored,
 * safe to run in any environment.
 *
 * Note on what "superadmin" actually grants here: AuthorizationService::
 * hasPermission() special-cases isSuperAdmin() to return true for every
 * permission slug except three clinical-safety ones (signing notes,
 * dispensing medication, verifying lab results), which still require a real
 * role→permission grant. That's intentional — a pure administrative account
 * shouldn't be able to perform clinical acts just by holding admin rights —
 * so this command does not attempt to grant those three.
 *
 * Also worth knowing before relying on this: Role (and therefore any role
 * assignment, including this one) is tenant-scoped via BelongsToTenant. This
 * account is a full admin *within the tenant it's created in* — it is not
 * the cross-tenant platform-operator account the /superadmin workspace's
 * tenant-switching UI implies. That workspace's cross-tenant views don't
 * currently work correctly under RLS enforcement regardless of which role
 * you hold; that's a separate, unaddressed gap in that newer feature.
 */
class CreateSuperAdminCommand extends Command
{
    protected $signature = 'afya:create-superadmin
        {--tenant= : Tenant name (created if it does not exist)}
        {--tenant-slug= : Tenant slug (defaults to a slugified tenant name)}
        {--email= : Admin login email}
        {--first-name= : Admin first name}
        {--last-name= : Admin last name}
        {--password= : Admin password (prompted securely if omitted)}';

    protected $description = 'Bootstrap a single platform-superadmin account outside of DatabaseSeeder — safe to run in production.';

    public function handle(): int
    {
        $tenantName = $this->option('tenant') ?: $this->ask('Tenant name', 'Default Tenant');
        $tenantSlug = $this->option('tenant-slug') ?: Str::slug($tenantName);

        $email = $this->option('email') ?: $this->ask('Admin email');
        $firstName = $this->option('first-name') ?: $this->ask('First name', 'System');
        $lastName = $this->option('last-name') ?: $this->ask('Last name', 'Administrator');
        $password = $this->option('password') ?: $this->secret('Admin password (input hidden)');

        if (! $email || ! $password) {
            $this->error('Email and password are required.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");

            return self::FAILURE;
        }

        return DB::transaction(function () use ($tenantName, $tenantSlug, $email, $firstName, $lastName, $password) {
            $tenant = Tenant::firstOrCreate(
                ['slug' => $tenantSlug],
                ['name' => $tenantName, 'status' => 'active']
            );

            // Console commands never pass through TenantContextMiddleware, so
            // — same as DatabaseSeeder — the RLS session variable has to be
            // set explicitly here, or every insert below is rejected by the
            // FORCE ROW LEVEL SECURITY policies now that they're genuinely
            // enforced.
            app(TenantContext::class)->setTenantId($tenant->id);
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $tenant->id]);
            }

            \App\Domains\Tenancy\Actions\SyncTenantStandardRolesAction::ensureMasterPermissions();

            $permission = Permission::firstOrCreate(
                ['slug' => 'platform.superadmin.access'],
                ['name' => 'Superadmin Platform Access', 'domain' => 'Platform']
            );

            $role = Role::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'super-admin'],
                ['name' => 'Platform Superadmin', 'is_system' => true, 'description' => 'Full administrative access within this tenant.']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password_hash' => Hash::make($password),
                'status' => 'active',
                'two_factor_enabled' => false,
            ]);

            RoleAssignment::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                // facility_id/department_id left null: a global, tenant-wide
                // assignment, matching how AuthorizationService::isSuperAdmin()
                // looks for this role (whereNull('facility_id')).
            ]);

            $this->newLine();
            $this->info('Superadmin account created.');
            $this->line("  Tenant:  {$tenant->name} ({$tenant->slug})");
            $this->line("  Email:   {$email}");
            $this->line('  Role:    super-admin (global, this tenant)');
            $this->newLine();
            $this->comment('Note: clinical.notes.sign / pharmacy.dispense.execute / lab.result.verify are NOT granted — those require an actual clinical role by design.');

            return self::SUCCESS;
        });
    }
}
