<?php

use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Actions\AssignUserRoleAction;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Kairuki Hospital Group', 'slug' => 'kairuki', 'domain' => 'kairuki.local', 'is_active' => true]);
    setTestTenantContext($this->tenant->id);

    $this->facility = Facility::create(['name' => 'Main Campus', 'facility_code' => 'KMC-01', 'facility_type' => 'Hospital']);

    $this->user = User::create([
        'username' => 'dr_massawe',
        'email' => 'massawe@kairuki.local',
        'first_name' => 'Jane',
        'last_name' => 'Massawe',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $this->patient = Patient::create([
        'first_name' => 'Asha',
        'last_name' => 'Juma',
        'primary_mrn' => 'MRN-2026-0001',
        'date_of_birth' => '1990-05-15',
        'gender' => 'Female',
        'status' => 'Active',
    ]);

    $role = Role::create(['tenant_id' => $this->tenant->id, 'slug' => 'doctor', 'name' => 'Doctor']);
    $billingView = Permission::firstOrCreate(
        ['slug' => 'billing.invoice.view'],
        ['name' => 'View Invoices', 'domain' => 'Billing']
    );
    $encounterView = Permission::firstOrCreate(
        ['slug' => 'clinical.encounter.view'],
        ['name' => 'View Encounters', 'domain' => 'Clinical']
    );
    $patientView = Permission::firstOrCreate(
        ['slug' => 'patient.registry.view'],
        ['name' => 'View Patients', 'domain' => 'Patient']
    );
    $appointmentView = Permission::firstOrCreate(
        ['slug' => 'scheduling.appointment.view'],
        ['name' => 'View Appointments', 'domain' => 'Scheduling']
    );
    $role->permissions()->syncWithoutDetaching([$billingView->id, $encounterView->id, $patientView->id, $appointmentView->id]);
    app(AssignUserRoleAction::class)->execute($this->user->id, $role->id);
});

test('dashboard workspace renders with layout primitives for authenticated user', function () {
    Invoice::create([
        'patient_id' => $this->patient->id,
        'facility_id' => $this->facility->id,
        'invoice_number' => 'INV-TEST-001',
        'status' => 'Partially Paid',
        'issued_at' => now(),
        'total_amount' => 30000.00,
        'paid_amount' => 15000.00,
    ]);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/HomeWorkspace')
            ->where('can.billing', true)
            ->where('metrics.unpaid_invoices', 1)
            ->where('metrics.total_patients', 1)
        );
});

test('cashier dashboard accurately counts unpaid invoices with open, draft, or partially paid status', function () {
    $cashierUser = User::create([
        'username' => 'cashier_mollel',
        'email' => 'cashier@kairuki.local',
        'first_name' => 'Grace',
        'last_name' => 'Mollel',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $cashierRole = Role::create(['tenant_id' => $this->tenant->id, 'slug' => 'cashier', 'name' => 'Cashier']);
    $billingPerm = Permission::firstOrCreate(['slug' => 'billing.invoice.view'], ['name' => 'View Invoices', 'domain' => 'Billing']);
    $patientPerm = Permission::firstOrCreate(['slug' => 'patient.registry.view'], ['name' => 'View Patients', 'domain' => 'Patient']);
    $cashierRole->permissions()->syncWithoutDetaching([$billingPerm->id, $patientPerm->id]);
    app(AssignUserRoleAction::class)->execute($cashierUser->id, $cashierRole->id);

    Invoice::create([
        'patient_id' => $this->patient->id,
        'facility_id' => $this->facility->id,
        'invoice_number' => 'INV-OPEN-001',
        'status' => 'Open',
        'total_amount' => 20000.00,
        'paid_amount' => 0.00,
    ]);

    Invoice::create([
        'patient_id' => $this->patient->id,
        'facility_id' => $this->facility->id,
        'invoice_number' => 'INV-PAID-001',
        'status' => 'Paid',
        'total_amount' => 10000.00,
        'paid_amount' => 10000.00,
    ]);

    $this->actingAs($cashierUser)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/HomeWorkspace')
            ->where('can.billing', true)
            ->where('can.clinical', false)
            ->where('can.pharmacy', false)
            ->where('metrics.unpaid_invoices', 1)
            ->where('metrics.active_encounters', null)
            ->where('metrics.pending_pharmacy', null)
        );
});

test('billing workspace renders with active invoices and cashier pos desk', function () {
    Invoice::create([
        'patient_id' => $this->patient->id,
        'facility_id' => $this->facility->id,
        'invoice_number' => 'INV-TEST-001',
        'status' => 'Issued',
        'issued_at' => now(),
        'total_amount' => 30000.00,
        'paid_amount' => 15000.00,
    ]);

    $this->actingAs($this->user)
        ->get('/billing/desk')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/BillingWorkspace')
            ->has('invoices', 1)
        );
});

test('clinical workspace renders encounter and strictly contextual patient summary', function () {
    $encounter = Encounter::create([
        'patient_id' => $this->patient->id,
        'facility_id' => $this->facility->id,
        'provider_id' => $this->user->id,
        'encounter_type' => 'OPD',
        'status' => 'In Progress',
        'start_time' => now(),
    ]);

    $this->actingAs($this->user)
        ->get("/encounters/{$encounter->id}/workspace")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/ClinicalWorkspace')
            ->has('encounter')
            ->where('encounter.id', $encounter->id)
            ->where('encounter.patient.first_name', 'Asha')
        );
});

test('dashboard strictly displays today appointments and excludes past appointments', function () {
    Appointment::create([
        'patient_id' => $this->patient->id,
        'facility_id' => $this->facility->id,
        'scheduled_time' => now()->subDay(),
        'duration_minutes' => 30,
        'appointment_type' => 'Consultation',
        'status' => 'Completed',
    ]);

    $todayAppointment = Appointment::create([
        'patient_id' => $this->patient->id,
        'facility_id' => $this->facility->id,
        'scheduled_time' => now()->addHours(2),
        'duration_minutes' => 30,
        'appointment_type' => 'Consultation',
        'status' => 'Scheduled',
    ]);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/HomeWorkspace')
            ->has('todayAppointments', 1)
            ->where('todayAppointments.0.id', $todayAppointment->id)
        );
});
