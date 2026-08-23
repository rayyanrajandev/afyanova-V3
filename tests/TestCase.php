<?php

namespace Tests;

use App\Core\Context\TenantContext;
use App\Domains\Billing\Models\ChargeMasterItem;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

abstract class TestCase extends BaseTestCase
{
    protected Tenant $tenant;

    protected Facility $facility;

    protected User $user;

    protected function setupTenantEnvironment(): array
    {
        $this->tenant = Tenant::create([
            'name' => 'Test Hospital',
            'slug' => 'test-hospital',
            'domain' => 'test-hospital.local',
            'status' => 'active',
        ]);

        $context = app(TenantContext::class);
        $context->setTenantId($this->tenant->id);

        $this->facility = Facility::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Wing',
            'code' => 'MAIN-01',
            'is_active' => true,
        ]);

        $doctorRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Doctor',
            'slug' => 'doctor',
        ]);

        // Baseline permissions this fixture's "Doctor" is exercised with
        // across the domain test suite — a real doctor plausibly holds all
        // of these day to day, so granting them here once avoids repeating
        // the same Permission/Role wiring in every test file that touches
        // one of these guarded actions.
        $baselinePermissions = [
            'clinical.notes.sign' => ['name' => 'Sign Clinical Notes', 'domain' => 'Clinical'],
            'clinical.encounter.create' => ['name' => 'Start Encounter', 'domain' => 'Clinical'],
            'lab.order.create' => ['name' => 'Order Lab Investigations', 'domain' => 'Clinical'],
            'procedure.order.create' => ['name' => 'Order Procedures', 'domain' => 'Procedure'],
            'reports.analytics.view' => ['name' => 'View Analytics & Reports', 'domain' => 'Reports'],
            'clinical.consent.record' => ['name' => 'Record Informed Consent', 'domain' => 'Clinical'],
            'clinical.referral.create' => ['name' => 'Create Inter-Facility Referral', 'domain' => 'Clinical'],
            'clinical.immunization.administer' => ['name' => 'Administer Immunization', 'domain' => 'Clinical'],
            'clinical.anc.record' => ['name' => 'Record ANC Visit', 'domain' => 'Clinical'],
            'clinical.partograph.record' => ['name' => 'Record Partograph Entry', 'domain' => 'Clinical'],
            'clinical.problem-list.manage' => ['name' => 'Manage Problem List', 'domain' => 'Clinical'],
            'pharmacy.medication-reconciliation.record' => ['name' => 'Record Medication Reconciliation', 'domain' => 'Pharmacy'],
            'radiology.order.create' => ['name' => 'Order Diagnostic Imaging', 'domain' => 'Radiology'],
            'radiology.report.sign' => ['name' => 'Sign Radiology Report', 'domain' => 'Radiology'],
            'radiology.report.amend' => ['name' => 'Amend Radiology Report', 'domain' => 'Radiology'],
        ];

        foreach ($baselinePermissions as $slug => $attrs) {
            $permission = Permission::firstOrCreate(['slug' => $slug], $attrs);
            $doctorRole->permissions()->syncWithoutDetaching([$permission->id]);
        }

        // Baseline charge master entry GenerateInvoiceAction resolves to
        // when no explicit price is passed.
        ChargeMasterItem::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'CONSULT-OPD'],
            [
                'name' => 'General OPD Consultation',
                'category' => 'Consultation',
                'unit_price' => 20000.00,
                'currency' => 'TZS',
                'effective_from' => now()->subYear()->toDateString(),
                'is_active' => true,
            ]
        );

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Dr. Jane',
            'last_name' => 'Massawe',
            'email' => 'doctor@test.local',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        DB::table('role_assignments')->insert([
            'id' => Uuid::uuid7()->toString(),
            'user_id' => $this->user->id,
            'role_id' => $doctorRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'tenant' => $this->tenant,
            'facility' => $this->facility,
            'user' => $this->user,
        ];
    }
}
