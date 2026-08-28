<?php

namespace Tests\Feature;

use App\Domains\Clinical\Models\Allergy;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Clinical\Services\ClinicalDecisionSupportService;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalDecisionSupportTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Facility $facility;
    protected User $doctor;
    protected Patient $patient;
    protected ClinicalDecisionSupportService $cdssService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'KCMC Referral Hospital',
            'slug' => 'kcmc-referral',
            'domain' => 'kcmc.test',
            'status' => 'active',
        ]);

        setTestTenantContext($this->tenant->id);

        $this->facility = Facility::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Medical Branch',
            'code' => 'KCMC-MAIN',
            'facility_type' => 'Hospital',
            'is_active' => true,
        ]);

        $this->doctor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->patient = Patient::create([
            'tenant_id' => $this->tenant->id,
            'registered_at_facility_id' => $this->facility->id,
            'primary_mrn' => 'MRN-KCMC-001',
            'first_name' => 'John',
            'last_name' => 'Mrema',
            'dob' => now()->subYears(40)->toDateString(),
            'gender' => 'Male',
            'phone' => '255712345678',
        ]);

        $this->cdssService = app(ClinicalDecisionSupportService::class);
    }

    public function test_detects_critical_drug_drug_interaction_between_warfarin_and_ibuprofen(): void
    {
        $warfarin = MedicationFormulary::create([
            'tenant_id' => $this->tenant->id,
            'generic_name' => 'Warfarin Sodium',
            'brand_name' => 'Warfarin 5mg Tablets',
            'form' => 'Tablet',
            'strength' => '5mg',
            'route' => 'PO',
            'is_active' => true,
        ]);

        $ibuprofen = MedicationFormulary::create([
            'tenant_id' => $this->tenant->id,
            'generic_name' => 'Ibuprofen',
            'brand_name' => 'Ibuprofen 400mg Tablets',
            'form' => 'Tablet',
            'strength' => '400mg',
            'route' => 'PO',
            'is_active' => true,
        ]);

        $result = $this->cdssService->evaluatePrescription(
            patientId: $this->patient->id,
            items: [
                ['medication_id' => $ibuprofen->id, 'dosage' => '400mg', 'frequency' => 'TID'],
            ],
            existingPrescriptions: [
                ['medication_id' => $warfarin->id, 'dosage' => '5mg', 'frequency' => 'OD'],
            ]
        );

        $this->assertFalse($result['is_safe']);
        $this->assertGreaterThanOrEqual(1, $result['critical_count']);
        $this->assertTrue($result['requires_override']);

        $ddiAlert = collect($result['alerts'])->firstWhere('type', 'DRUG_DRUG_INTERACTION');
        $this->assertNotNull($ddiAlert);
        $this->assertStringContainsString('Bleeding', $ddiAlert['description']);
    }

    public function test_detects_allergy_cross_reactivity_for_penicillin_allergic_patient_prescribed_cephalosporin(): void
    {
        // Record Penicillin allergy
        Allergy::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->doctor->id,
            'allergen_type' => 'Medication',
            'allergen' => 'Penicillin',
            'reaction' => 'Anaphylaxis & Rash',
            'severity' => 'Severe',
            'status' => 'Active',
            'is_deprecated' => false,
        ]);

        $ceftriaxone = MedicationFormulary::create([
            'tenant_id' => $this->tenant->id,
            'generic_name' => 'Ceftriaxone Sodium',
            'brand_name' => 'Ceftriaxone 1g Injection',
            'form' => 'Injection',
            'strength' => '1g',
            'route' => 'IV',
            'is_active' => true,
        ]);

        $result = $this->cdssService->evaluatePrescription(
            patientId: $this->patient->id,
            items: [
                ['medication_id' => $ceftriaxone->id, 'dosage' => '1g', 'frequency' => 'OD'],
            ]
        );

        $crossAlert = collect($result['alerts'])->firstWhere('type', 'ALLERGY_CROSS_REACTIVITY');
        $this->assertNotNull($crossAlert);
        $this->assertStringContainsString('Cephalosporin Cross-Reactivity', $crossAlert['title']);
    }

    public function test_detects_renal_contraindication_for_metformin_when_egfr_is_severely_reduced(): void
    {
        // Record lab test with elevated serum creatinine (250 µmol/L)
        $labTest = LabTest::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Serum Creatinine',
            'test_code' => 'LAB-CREAT',
            'category' => 'Biochemistry',
            'specimen_type' => 'Blood',
            'is_active' => true,
        ]);

        $encounter = \App\Domains\Clinical\Models\Encounter::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'provider_id' => $this->doctor->id,
            'encounter_type' => 'OPD',
            'status' => 'Completed',
        ]);

        $labOrder = LabOrder::create([
            'tenant_id' => $this->tenant->id,
            'encounter_id' => $encounter->id,
            'patient_id' => $this->patient->id,
            'ordered_by_id' => $this->doctor->id,
            'order_number' => 'ORD-101',
            'status' => 'Completed',
        ]);

        LabOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'lab_order_id' => $labOrder->id,
            'lab_test_id' => $labTest->id,
            'price' => 15000,
            'status' => 'Completed',
            'results' => ['numeric_value' => 280], // High creatinine -> low eGFR (< 30)
            'is_deprecated' => false,
        ]);

        $metformin = MedicationFormulary::create([
            'tenant_id' => $this->tenant->id,
            'generic_name' => 'Metformin HCl',
            'brand_name' => 'Metformin 500mg Tablets',
            'form' => 'Tablet',
            'strength' => '500mg',
            'route' => 'PO',
            'is_active' => true,
        ]);

        $result = $this->cdssService->evaluatePrescription(
            patientId: $this->patient->id,
            items: [
                ['medication_id' => $metformin->id, 'dosage' => '500mg', 'frequency' => 'BD'],
            ]
        );

        $this->assertNotNull($result['egfr_info']);
        $this->assertLessThan(30, $result['egfr_info']['egfr']);

        $renalAlert = collect($result['alerts'])->firstWhere('type', 'RENAL_CONTRAINDICATION');
        $this->assertNotNull($renalAlert);
        $this->assertStringContainsString('Lactic Acidosis', $renalAlert['title']);
    }

    public function test_calculates_critical_mews_deterioration_score_for_unstable_patient(): void
    {
        $criticalVitals = [
            'systolic_bp' => 70, // +3
            'heart_rate' => 135, // +3
            'respiratory_rate' => 30, // +3
            'temperature_c' => 39.5, // +2
            'oxygen_saturation' => 88, // +3
            'avpu' => 'P', // +2
        ];

        $mews = $this->cdssService->calculateMews($criticalVitals);

        $this->assertGreaterThanOrEqual(6, $mews['total_score']);
        $this->assertEquals('CRITICAL', $mews['risk_level']);
        $this->assertNotEmpty($mews['escalation_protocol']);
    }

    public function test_calculates_low_mews_score_for_stable_normal_vitals(): void
    {
        $normalVitals = [
            'systolic_bp' => 120,
            'heart_rate' => 72,
            'respiratory_rate' => 16,
            'temperature_c' => 36.8,
            'oxygen_saturation' => 99,
            'avpu' => 'A',
        ];

        $mews = $this->cdssService->calculateMews($normalVitals);

        $this->assertLessThanOrEqual(1, $mews['total_score']);
        $this->assertEquals('LOW', $mews['risk_level']);
    }
}
