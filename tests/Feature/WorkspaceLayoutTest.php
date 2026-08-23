<?php

use App\Core\Context\TenantContext;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Kairuki Hospital Group', 'slug' => 'kairuki', 'domain' => 'kairuki.local', 'is_active' => true]);
    app(TenantContext::class)->setTenantId($this->tenant->id);

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
});

test('dashboard workspace renders with layout primitives for authenticated user', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/HomeWorkspace')
        );
});

test('design foundation demo renders for authenticated user', function () {
    $this->actingAs($this->user)
        ->get('/workspace/foundation')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/DesignFoundationDemo')
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
