<?php

namespace App\Domains\Tenancy\Actions;

use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Billing\Models\LedgerAccount;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\RoleAssignment;
use App\Domains\Tenancy\Models\Department;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\SubscriptionPlan;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProvisionTenantAction
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function execute(array $data): Tenant
    {
        $previousTenantId = null;
        if (DB::getDriverName() === 'pgsql') {
            $previousTenantId = DB::scalar("SELECT current_setting('app.current_tenant_id', true)");
        }

        try {
            return DB::transaction(function () use ($data) {
                $slug = Str::slug($data['slug'] ?? $data['name']);
                if (Tenant::where('slug', $slug)->exists()) {
                    throw new InvalidArgumentException("A hospital organization with the slug '{$slug}' already exists.");
                }

                $tier = $data['subscription_tier'] ?? 'growth';
                $planBlueprint = SubscriptionPlan::where('code', $tier)->first();
                
                $maxFacilities = $planBlueprint?->max_facilities ?? match ($tier) {
                    'starter' => 1,
                    'growth' => 5,
                    'enterprise' => 50,
                    default => 5,
                };
                $maxUsers = $planBlueprint?->max_users ?? match ($tier) {
                    'starter' => 15,
                    'growth' => 75,
                    'enterprise' => 500,
                    default => 50,
                };
                $storageQuota = $planBlueprint?->storage_quota_mb ?? 10240;
                $defaultFeatures = $planBlueprint?->feature_flags ?? match ($tier) {
                    'starter' => ['billing', 'pharmacy', 'laboratory', 'inpatient', 'sms'],
                    'growth' => ['billing', 'pharmacy', 'laboratory', 'inpatient', 'theatre', 'insurance', 'sms', 'mpesa', 'bi_analytics'],
                    'enterprise' => ['billing', 'pharmacy', 'laboratory', 'inpatient', 'theatre', 'insurance', 'radiology', 'sms', 'mpesa', 'bi_analytics', 'fhir', 'dicom'],
                    default => ['billing', 'pharmacy', 'laboratory', 'inpatient', 'sms', 'mpesa'],
                };

                // 1. Create Tenant
                $tenant = Tenant::create([
                    'name' => $data['name'],
                    'slug' => $slug,
                    'domain' => $data['domain'] ?? ($slug . '.afyanova.local'),
                    'status' => 'active',
                    'plan' => $tier,
                    'subscription_tier' => $tier,
                    'subscription_status' => $data['subscription_status'] ?? 'active',
                    'max_facilities' => $data['max_facilities'] ?? $maxFacilities,
                    'max_users' => $data['max_users'] ?? $maxUsers,
                    'storage_quota_mb' => $data['storage_quota_mb'] ?? $storageQuota,
                    'feature_flags' => $data['feature_flags'] ?? $defaultFeatures,
                    'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
                    'billing_contact_email' => $data['admin_email'] ?? ($data['billing_contact_email'] ?? null),
                    'billing_contact_phone' => $data['admin_phone'] ?? ($data['billing_contact_phone'] ?? null),
                    'trial_ends_at' => ($data['subscription_status'] ?? '') === 'trial' ? now()->addDays(30) : null,
                    'settings' => [
                        'currency' => 'TZS',
                        'timezone' => 'Africa/Dar_es_Salaam',
                        'country' => 'Tanzania',
                    ],
                ]);

                // Dynamically set PostgreSQL session tenant_id so RLS policy WITH CHECK passes for child rows
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $tenant->id]);
                }

                // 2. Create Primary Main Facility Branch
                $facility = Facility::create([
                    'tenant_id' => $tenant->id,
                    'name' => !empty($data['main_facility_name']) ? $data['main_facility_name'] : ($tenant->name . ' - Main Branch'),
                    'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $slug), 0, 4)) . '-MAIN',
                    'facility_type' => $data['facility_type'] ?? 'Hospital',
                    'physical_address' => $data['physical_address'] ?? 'Central Hospital Avenue',
                    'city' => $data['city'] ?? 'Dar es Salaam',
                    'region' => $data['region'] ?? 'Dar es Salaam',
                    'contact_email' => $data['admin_email'] ?? null,
                    'contact_phone' => $data['admin_phone'] ?? null,
                    'is_active' => true,
                ]);

                // 3. Create Standard Clinical Departments
                $standardDepts = [
                    ['name' => 'Outpatient Department (OPD)', 'code' => 'OPD'],
                    ['name' => 'Emergency Medicine (EMD)', 'code' => 'EMD'],
                    ['name' => 'Inpatient Medical/Surgical Ward', 'code' => 'IPD'],
                    ['name' => 'Main Pharmacy', 'code' => 'PHARM'],
                    ['name' => 'Clinical Pathology Laboratory', 'code' => 'LAB'],
                    ['name' => 'Main Operating Theatre', 'code' => 'OT'],
                    ['name' => 'Billing & Cashier Accounts', 'code' => 'BILL'],
                ];

                foreach ($standardDepts as $dept) {
                    Department::create([
                        'tenant_id' => $tenant->id,
                        'facility_id' => $facility->id,
                        'name' => $dept['name'],
                        'code' => $dept['code'],
                        'is_clinical' => ! in_array($dept['code'], ['BILL']),
                        'is_active' => true,
                    ]);
                }

                // 4. Create Initial Tenant Administrator User
                $adminUser = User::create([
                    'tenant_id' => $tenant->id,
                    'first_name' => $data['admin_first_name'] ?? 'Admin',
                    'last_name' => $data['admin_last_name'] ?? $tenant->name,
                    'email' => $data['admin_email'],
                    'phone' => $data['admin_phone'] ?? null,
                    'password_hash' => Hash::make($data['admin_password'] ?? 'Password123!'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                // Create and assign tenant-admin role
                $adminRole = Role::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'slug' => 'tenant-admin',
                    ],
                    [
                        'name' => 'Hospital Administrator',
                        'is_system' => true,
                        'description' => 'Full administrative access across all facilities, departments, and clinical settings.',
                    ]
                );

                $permissions = Permission::all();
                if ($permissions->isNotEmpty()) {
                    $adminRole->permissions()->sync($permissions->pluck('id')->toArray());
                }

                RoleAssignment::firstOrCreate([
                    'user_id' => $adminUser->id,
                    'role_id' => $adminRole->id,
                    'facility_id' => null, // Global tenant scope
                    'department_id' => null,
                ]);

                // 5. Seed Default Chart of Accounts for Billing Ledger
                $standardAccounts = [
                    ['code' => '1000', 'name' => 'Petty Cash / Cashier Till', 'type' => 'Asset'],
                    ['code' => '1010', 'name' => 'Main Bank Operating Account', 'type' => 'Asset'],
                    ['code' => '1020', 'name' => 'Lipa na M-Pesa Mobile Collections', 'type' => 'Asset'],
                    ['code' => '1100', 'name' => 'Accounts Receivable - Patients', 'type' => 'Asset'],
                    ['code' => '1110', 'name' => 'Accounts Receivable - NHIF / Insurance', 'type' => 'Asset'],
                    ['code' => '2000', 'name' => 'Patient Advance Deposits Liability', 'type' => 'Liability'],
                    ['code' => '4000', 'name' => 'Clinical Consultation Revenue', 'type' => 'Revenue'],
                    ['code' => '4010', 'name' => 'Inpatient Bed & Board Revenue', 'type' => 'Revenue'],
                    ['code' => '4020', 'name' => 'Laboratory Diagnostic Revenue', 'type' => 'Revenue'],
                    ['code' => '4030', 'name' => 'Pharmacy Dispensary Revenue', 'type' => 'Revenue'],
                    ['code' => '4040', 'name' => 'Surgical Procedures & OT Revenue', 'type' => 'Revenue'],
                ];

                foreach ($standardAccounts as $acc) {
                    LedgerAccount::create([
                        'tenant_id' => $tenant->id,
                        'code' => $acc['code'],
                        'name' => $acc['name'],
                        'type' => $acc['type'],
                    ]);
                }

                $this->auditLogger->log([
                    'tenant_id' => $tenant->id,
                    'facility_id' => $facility->id,
                    'user_id' => $adminUser->id,
                    'event_category' => 'PLATFORM_SUPERADMIN',
                    'action' => 'TENANT_PROVISIONED',
                    'entity_type' => Tenant::class,
                    'entity_id' => $tenant->id,
                    'before_state' => null,
                    'after_state' => json_encode([
                        'tenant_id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'subscription_tier' => $tenant->subscription_tier,
                        'admin_user_id' => $adminUser->id,
                        'admin_email' => $adminUser->email,
                    ]),
                    'justification_reason' => 'Superadmin automated hospital organization onboarding wizard.',
                ]);

                return $tenant;
            });
        } finally {
            if (DB::getDriverName() === 'pgsql' && $previousTenantId !== null) {
                DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $previousTenantId]);
            }
        }
    }
}
