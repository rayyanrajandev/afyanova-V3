<?php

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Actions\AssignUserRoleAction;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Insurance\Models\InsuranceProvider;
use App\Domains\Insurance\Models\PatientPolicy;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;
use App\Domains\Reports\Actions\GenerateFinancialAnalyticsAction;
use App\Domains\Reports\Actions\GenerateMorbidityAnalyticsAction;
use App\Domains\Reports\Actions\GenerateOperationalEfficiencyAction;
use App\Domains\Reports\Actions\GeneratePharmacoeconomicAnalyticsAction;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'AfyaNova National Hospital',
        'slug' => 'afyanova-national',
        'domain' => 'afyanova-national.test',
        'status' => 'Active',
    ]);

    setTestTenantContext($this->tenant->id);

    $this->facility = Facility::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Referral Complex',
        'code' => 'FAC-REP-01',
        'type' => 'ReferralHospital',
        'status' => 'Active',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Rajani',
        'last_name' => 'Massawe',
        'email' => 'dr.rajani@afyanova.local',
    ]);

    $adminRole = Role::create(['tenant_id' => $this->tenant->id, 'slug' => 'executive', 'name' => 'Executive']);
    $reportsPermission = Permission::firstOrCreate(
        ['slug' => 'reports.analytics.view'],
        ['name' => 'View Analytics & Reports', 'domain' => 'Reports']
    );
    $adminRole->permissions()->attach($reportsPermission->id);
    app(AssignUserRoleAction::class)->execute($this->user->id, $adminRole->id);

    $this->patient = Patient::create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Neema',
        'last_name' => 'Kassim',
        'dob' => '2001-05-15',
        'gender' => 'Female',
        'primary_mrn' => 'MRN-REP-001',
    ]);

    $this->childPatient = Patient::create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Baraka',
        'last_name' => 'Juma',
        'dob' => '2023-01-10',
        'gender' => 'Male',
        'primary_mrn' => 'MRN-REP-002',
    ]);

    $this->encounter = Encounter::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'patient_id' => $this->patient->id,
        'provider_id' => $this->user->id,
        'encounter_type' => 'OPD Consultation',
        'status' => 'In Progress',
        'start_time' => now()->subMinutes(30),
    ]);
});

test('morbidity analytics action aggregates top 10 diagnoses and identifies notifiable conditions', function () {
    // Seed sample diagnoses
    Diagnosis::create([
        'tenant_id' => $this->tenant->id,
        'encounter_id' => $this->encounter->id,
        'patient_id' => $this->patient->id,
        'icd_10_code' => 'B54',
        'description' => 'Unspecified Malaria',
        'type' => 'Primary',
        'certainty' => 'Confirmed',
        'diagnosed_by' => $this->user->id,
    ]);

    Diagnosis::create([
        'tenant_id' => $this->tenant->id,
        'encounter_id' => $this->encounter->id,
        'patient_id' => $this->childPatient->id,
        'icd_10_code' => 'B54',
        'description' => 'Unspecified Malaria',
        'type' => 'Primary',
        'certainty' => 'Confirmed',
        'diagnosed_by' => $this->user->id,
    ]);

    // Seed Notifiable Cholera case
    Diagnosis::create([
        'tenant_id' => $this->tenant->id,
        'encounter_id' => $this->encounter->id,
        'patient_id' => $this->patient->id,
        'icd_10_code' => 'A00.9',
        'description' => 'Cholera, unspecified',
        'type' => 'Primary',
        'certainty' => 'Confirmed',
        'diagnosed_by' => $this->user->id,
    ]);

    $action = app(GenerateMorbidityAnalyticsAction::class);
    $result = $action->execute($this->tenant->id);

    expect($result['total_diagnoses'])->toBe(3)
        ->and($result['top_10_morbidity'])->toHaveCount(2)
        ->and($result['top_10_morbidity'][0]['icd10_code'])->toBe('B54')
        ->and($result['top_10_morbidity'][0]['total_cases'])->toBe(2)
        ->and($result['top_10_morbidity'][0]['demographics']['under_5'])->toBe(1)
        ->and($result['notifiable_alert_count'])->toBe(1)
        ->and($result['notifiable_alerts'][0]['disease_name'])->toBe('Cholera');
});

test('financial analytics action aggregates departmental cost centers and payer mix', function () {
    $invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'patient_id' => $this->patient->id,
        'encounter_id' => $this->encounter->id,
        'invoice_number' => 'INV-REP-001',
        'total_amount' => 85000.00,
        'paid_amount' => 50000.00,
        'status' => 'Partially Paid',
    ]);

    InvoiceLineItem::create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'description' => 'Specialist Consultation',
        'category' => 'Consultation',
        'quantity' => 1,
        'unit_price' => 25000.00,
        'total_price' => 25000.00,
    ]);

    InvoiceLineItem::create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'description' => 'Artemether/Lumefantrine & Paracetamol',
        'category' => 'Pharmacy',
        'quantity' => 1,
        'unit_price' => 60000.00,
        'total_price' => 60000.00,
    ]);

    // Seed Insurance Claim
    $provider = InsuranceProvider::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'National Health Insurance (NHIF)',
        'code' => 'NHIF',
        'status' => 'Active',
    ]);

    $policy = PatientPolicy::create([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $this->patient->id,
        'insurance_provider_id' => $provider->id,
        'card_number' => 'NHIF-REP-9988',
        'status' => 'Active',
    ]);

    InsuranceClaim::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'patient_id' => $this->patient->id,
        'patient_policy_id' => $policy->id,
        'encounter_id' => $this->encounter->id,
        'invoice_id' => $invoice->id,
        'claim_number' => 'CLM-REP-001',
        'total_claimed_amount' => 85000.00,
        'approved_amount' => 80000.00,
        'status' => 'Approved',
    ]);

    $action = app(GenerateFinancialAnalyticsAction::class);
    $result = $action->execute($this->tenant->id);

    expect($result['summary']['total_billed_tzs'])->toBe(85000.00)
        ->and($result['summary']['total_collected_tzs'])->toBe(50000.00)
        ->and($result['summary']['total_outstanding_tzs'])->toBe(35000.00)
        ->and($result['payer_mix']['insurance_claims']['claims_count'])->toBe(1)
        ->and($result['payer_mix']['insurance_claims']['payers'][0]['reimbursement_rate'])->toBe(94.1);
});

test('pharmacoeconomic action calculates inventory valuation, drug velocity and expiry risk', function () {
    $med = MedicationFormulary::create([
        'tenant_id' => $this->tenant->id,
        'generic_name' => 'Artemether + Lumefantrine',
        'brand_name' => 'Coartem 20/120',
        'form' => 'Tablet',
        'strength' => '20/120mg',
        'route' => 'Oral',
        'drug_class' => 'Antimalarial',
    ]);

    $batch = InventoryBatch::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'medication_id' => $med->id,
        'batch_number' => 'AL-2026-99',
        'initial_quantity' => 200,
        'current_quantity' => 150,
        'unit_cost' => 3000.00,
        'unit_selling_price' => 5000.00,
        'expiry_date' => now()->addDays(20), // Critical < 30 days
        'status' => 'Active',
    ]);

    StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'medication_id' => $med->id,
        'batch_id' => $batch->id,
        'movement_type' => 'Dispensed',
        'quantity_change' => -50,
        'quantity_before' => 200,
        'quantity_after' => 150,
        'performed_by' => $this->user->id,
    ]);

    $action = app(GeneratePharmacoeconomicAnalyticsAction::class);
    $result = $action->execute($this->tenant->id);

    expect($result['valuation']['total_cost_value_tzs'])->toBe(450000.00) // 150 * 3000
        ->and($result['valuation']['total_retail_value_tzs'])->toBe(750000.00) // 150 * 5000
        ->and($result['fast_moving_medications'][0]['units_dispensed'])->toBe(50)
        ->and($result['expiry_risk']['critical_30_days'])->toHaveCount(1);
});

test('operational efficiency action calculates bed occupancy rate and clinical throughput', function () {
    $ward = Ward::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'name' => 'St. Luke Male Surgical',
        'code' => 'WARD-MS',
        'type' => 'Surgical',
        'total_beds' => 2,
    ]);

    $bed1 = Bed::create([
        'tenant_id' => $this->tenant->id,
        'ward_id' => $ward->id,
        'bed_number' => 'MS-01',
        'type' => 'Standard',
        'status' => 'Occupied',
    ]);

    $bed2 = Bed::create([
        'tenant_id' => $this->tenant->id,
        'ward_id' => $ward->id,
        'bed_number' => 'MS-02',
        'type' => 'Standard',
        'status' => 'Available',
    ]);

    Admission::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'patient_id' => $this->patient->id,
        'encounter_id' => $this->encounter->id,
        'ward_id' => $ward->id,
        'bed_id' => $bed1->id,
        'admission_number' => 'ADM-REP-01',
        'admission_reason' => 'Acute Appendicitis',
        'admitted_at' => now()->subDays(3),
        'status' => 'Admitted',
        'admitting_doctor_id' => $this->user->id,
    ]);

    $action = app(GenerateOperationalEfficiencyAction::class);
    $result = $action->execute($this->tenant->id);

    expect($result['bed_occupancy']['total_beds'])->toBe(2)
        ->and($result['bed_occupancy']['occupied_beds'])->toBe(1)
        ->and($result['bed_occupancy']['bor_percent'])->toBe(50.0)
        ->and($result['inpatient_throughput']['active_inpatients'])->toBe(1);
});

test('reports workspace renders correctly with all intelligence datasets', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('reports.workspace'));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Workspace/ReportsWorkspace')
            ->has('morbidity')
            ->has('financial')
            ->has('pharmaco')
            ->has('operational')
            ->has('metrics')
            ->has('filters')
        );
});

test('a role holding only the real seeded auditor report slugs sees exactly those sections, not the blanket bypass', function () {
    // Mirrors the real DatabaseSeeder auditor grant minus reports.analytics.view,
    // to exercise the section-level can map rather than the blanket-bypass branch.
    $auditorRole = Role::create(['tenant_id' => $this->tenant->id, 'slug' => 'auditor', 'name' => 'Medical Auditor / Compliance']);
    $clinicalView = Permission::firstOrCreate(['slug' => 'reports.clinical.view'], ['name' => 'View Clinical Analytics', 'domain' => 'Reports']);
    $financialView = Permission::firstOrCreate(['slug' => 'reports.financial.view'], ['name' => 'View Financial Intelligence', 'domain' => 'Reports']);
    $auditorRole->permissions()->attach([$clinicalView->id, $financialView->id]);

    $auditorUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Peter',
        'last_name' => 'Lyimo',
        'email' => 'auditor@afyanova-national.test',
    ]);
    app(AssignUserRoleAction::class)->execute($auditorUser->id, $auditorRole->id);

    $this->actingAs($auditorUser)
        ->get(route('reports.workspace'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Workspace/ReportsWorkspace')
            ->where('can.morbidity', true)
            ->where('can.financial', true)
            ->where('can.pharmaco', false)
            ->where('can.operational', true)
        );
});
