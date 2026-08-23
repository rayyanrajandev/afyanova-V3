<?php

namespace Tests\Feature\Domains;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use App\Domains\Insurance\Actions\GenerateClaimFromEncounterAction;
use App\Domains\Insurance\Actions\RequestPreAuthAction;
use App\Domains\Insurance\Models\InsuranceProvider;
use App\Domains\Insurance\Models\InsuranceScheme;
use App\Domains\Insurance\Models\InsuranceTariff;
use App\Domains\Insurance\Models\PatientPolicy;
use App\Domains\Insurance\Models\PreAuthorization;
use App\Domains\Patient\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateInsuranceTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected Patient $patient;

    protected InsuranceProvider $jubilee;

    protected InsuranceScheme $goldScheme;

    protected PatientPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTenantEnvironment();

        $this->doctor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Dr. Paul',
            'last_name' => 'Kimaro',
        ]);

        $this->patient = Patient::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Baraka',
            'last_name' => 'Massawe',
            'dob' => '1988-03-20',
            'gender' => 'male',
            'primary_mrn' => 'MRN-PVT-009',
        ]);

        // 1. Private Insurer: Jubilee Insurance Tanzania
        $this->jubilee = InsuranceProvider::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'JUBILEE',
            'name' => 'Jubilee Insurance Tanzania',
            'provider_type' => 'PrivateHMO',
            'api_adapter' => 'jubilee_adapter',
            'is_active' => true,
        ]);

        // 2. Private Scheme with 10% Co-Pay
        $this->goldScheme = InsuranceScheme::create([
            'tenant_id' => $this->tenant->id,
            'insurance_provider_id' => $this->jubilee->id,
            'code' => 'JUB-CORP-GOLD',
            'name' => 'Jubilee Corporate Gold Tier',
            'co_pay_type' => 'Percentage',
            'co_pay_amount' => 10.00, // 10% patient co-pay
            'requires_pre_auth' => true,
            'is_active' => true,
        ]);

        // 3. Patient Policy Card
        $this->policy = PatientPolicy::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->patient->id,
            'insurance_provider_id' => $this->jubilee->id,
            'insurance_scheme_id' => $this->goldScheme->id,
            'card_number' => 'JUB-998822-01',
            'principal_member_name' => 'Baraka Massawe',
            'policy_start_date' => now()->subMonths(6)->toDateString(),
            'policy_expiry_date' => now()->addMonths(6)->toDateString(),
            'status' => 'Active',
            'biometric_verified' => true,
        ]);

        // 4. Negotiated Tariffs
        InsuranceTariff::create([
            'tenant_id' => $this->tenant->id,
            'insurance_provider_id' => $this->jubilee->id,
            'insurance_scheme_id' => $this->goldScheme->id,
            'item_type' => 'Consultation',
            'item_code' => 'CON-SPEC-001',
            'item_name' => 'Specialist Physician Consultation',
            'tariff_price' => 45000.00,
            'is_covered' => true,
        ]);
    }

    public function test_pre_authorization_can_be_requested_and_approved(): void
    {
        $action = new RequestPreAuthAction;

        $preAuth = $action->execute([
            'patient_policy_id' => $this->policy->id,
            'procedure_description' => 'Inpatient Laparoscopic Appendectomy Package',
            'requested_amount' => 1500000.00,
            'approved_amount' => 1400000.00,
            'auth_code' => 'JUB-AUTH-2026-8891',
            'status' => 'Approved',
            'notes' => 'Approved for 3-day inpatient stay under Jubilee Corporate Gold.',
        ]);

        $this->assertInstanceOf(PreAuthorization::class, $preAuth);
        $this->assertEquals('JUB-AUTH-2026-8891', $preAuth->auth_code);
        $this->assertEquals(1400000.00, $preAuth->approved_amount);
        $this->assertEquals('Approved', $preAuth->status);

        $this->assertDatabaseHas('pre_authorizations', [
            'tenant_id' => $this->tenant->id,
            'auth_code' => 'JUB-AUTH-2026-8891',
            'status' => 'Approved',
        ]);
    }

    public function test_claim_generator_creates_claim_with_proper_copay_and_scrubber(): void
    {
        // Create Encounter with Doctor
        $encounter = Encounter::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'provider_id' => $this->doctor->id,
            'encounter_type' => 'Inpatient',
            'status' => 'Completed',
            'started_at' => now()->subDay(),
        ]);

        // Add ICD-10 Diagnosis (Essential for Claim Scrubber)
        Diagnosis::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->patient->id,
            'encounter_id' => $encounter->id,
            'diagnosed_by' => $this->doctor->id,
            'code' => 'K35.80',
            'description' => 'Unspecified acute appendicitis',
            'type' => 'Primary',
        ]);

        // Add Inpatient Invoice Line Items
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'encounter_id' => $encounter->id,
            'invoice_number' => 'INV-PVT-001',
            'status' => 'Draft',
            'total_amount' => 100000.00,
            'paid_amount' => 0,
            'issued_at' => now(),
        ]);

        InvoiceLineItem::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'category' => 'Consultation',
            'description' => 'Specialist Physician Consultation',
            'quantity' => 1,
            'unit_price' => 45000.00,
            'total_price' => 45000.00,
        ]);

        InvoiceLineItem::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'category' => 'Pharmacy',
            'description' => 'Ceftriaxone 1g IV + IV Cannula Pack',
            'quantity' => 1,
            'unit_price' => 55000.00,
            'total_price' => 55000.00,
        ]);

        $action = new GenerateClaimFromEncounterAction;
        $claim = $action->execute($encounter, $this->policy);

        $this->assertEquals(100000.00, $claim->total_claimed_amount);
        // 10% of 100,000 = 10,000 co-pay
        $this->assertEquals(10000.00, $claim->co_pay_amount);
        $this->assertEquals(90000.00, $claim->approved_amount);
        $this->assertTrue($claim->scrubber_passed);
        $this->assertEquals('Vetted', $claim->status);

        $this->assertDatabaseHas('insurance_claims', [
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->patient->id,
            'total_claimed_amount' => 100000.00,
            'co_pay_amount' => 10000.00,
            'status' => 'Vetted',
        ]);
    }
}
