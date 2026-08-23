<?php

namespace Tests\Feature\Domains;

use App\Domains\Identity\Actions\AssignUserRoleAction;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InpatientMarTest extends TestCase
{
    use RefreshDatabase;

    protected User $nurse;

    protected User $witnessNurse;

    protected Ward $ward;

    protected Bed $bed;

    protected Patient $patient;

    protected Admission $admission;

    protected InventoryLocation $wardCabinet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTenantEnvironment();

        $this->nurse = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Sarah',
            'last_name' => 'Nurse',
        ]);

        $this->witnessNurse = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Mary',
            'last_name' => 'Witness',
        ]);

        $nurseRole = Role::create(['tenant_id' => $this->tenant->id, 'slug' => 'ward-nurse', 'name' => 'Ward Nurse']);
        $marPermission = Permission::create(['slug' => 'inpatient.mar.administer', 'name' => 'Administer MAR Dose', 'domain' => 'Inpatient']);
        $nurseRole->permissions()->attach($marPermission->id);
        app(AssignUserRoleAction::class)->execute($this->nurse->id, $nurseRole->id);

        $this->ward = Ward::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'name' => 'Maternity Ward',
            'code' => 'MAT-01',
            'daily_base_rate' => 50000,
        ]);

        $this->bed = Bed::create([
            'tenant_id' => $this->tenant->id,
            'ward_id' => $this->ward->id,
            'bed_number' => 'MAT-BED-01',
            'status' => 'Occupied',
        ]);

        $this->patient = Patient::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Amina',
            'last_name' => 'Juma',
            'dob' => '1995-05-12',
            'gender' => 'female',
            'primary_mrn' => 'MRN-IPD-001',
        ]);

        $this->admission = Admission::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'admitting_doctor_id' => $this->nurse->id,
            'admission_number' => 'ADM-2026-0001',
            'admitted_at' => now()->subDays(2),
            'status' => 'Admitted',
            'admission_reason' => 'Post-Caesarean Section Recovery',
        ]);

        $this->wardCabinet = InventoryLocation::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'name' => 'Maternity Ward Cabinet',
            'code' => 'CAB-MATERNITY',
            'type' => 'Ward_Cabinet',
            'is_active' => true,
        ]);
    }

    public function test_nurse_can_administer_mar_dose_and_deduct_ward_cabinet_stock(): void
    {
        $formulary = MedicationFormulary::create([
            'tenant_id' => $this->tenant->id,
            'generic_name' => 'Ceftriaxone Sodium',
            'brand_name' => 'Ceftriaxone 1g IV Powder',
            'form' => 'Injection',
            'strength' => '1g',
            'route' => 'IV',
            'is_active' => true,
        ]);

        $antibiotic = ItemMaster::create([
            'tenant_id' => $this->tenant->id,
            'medication_id' => $formulary->id,
            'item_code' => 'MED-CEFTRIAX-1G',
            'name' => 'Ceftriaxone 1g IV Powder',
            'category' => 'Pharmaceutical',
            'unit_cost_price' => 2500,
            'unit_selling_price' => 6000,
            'is_active' => true,
        ]);

        // Stock in Ward Cabinet
        $stock = InventoryStockBalance::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'location_id' => $this->wardCabinet->id,
            'medication_id' => $formulary->id,
            'quantity_on_hand' => 10,
        ]);

        $response = $this->actingAs($this->nurse)
            ->post(route('inpatient.admissions.mar.store', $this->admission), [
                'item_master_id' => $antibiotic->id,
                'item_name' => $antibiotic->name,
                'location_id' => $this->wardCabinet->id,
                'dose_quantity' => 1,
                'dose_unit' => 'vial',
                'route' => 'IV',
                'frequency' => 'BD',
                'status' => 'Administered',
                'charge_amount' => 6000,
                'notes' => 'Administered after test dose, patient comfortable',
            ]);

        $response->assertSessionHasNoErrors();

        // 1. Assert MAR created
        $this->assertDatabaseHas('medication_administration_records', [
            'tenant_id' => $this->tenant->id,
            'admission_id' => $this->admission->id,
            'item_name' => 'Ceftriaxone 1g IV Powder',
            'dose_quantity' => 1,
            'status' => 'Administered',
        ]);

        // 2. Assert Stock deducted from 10 to 9
        $stock->refresh();
        $this->assertEquals(9, $stock->quantity_on_hand);

        // 3. Assert Invoice Line Item created for billing
        $this->assertDatabaseHas('invoice_line_items', [
            'tenant_id' => $this->tenant->id,
            'unit_price' => 6000,
            'total_price' => 6000,
        ]);
    }

    public function test_administering_dda_narcotic_records_witness_and_dda_register_log(): void
    {
        $formulary = MedicationFormulary::create([
            'tenant_id' => $this->tenant->id,
            'generic_name' => 'Morphine Sulphate',
            'brand_name' => 'Morphine 10mg/ml Injection',
            'form' => 'Injection',
            'strength' => '10mg/ml',
            'route' => 'IV',
            'is_active' => true,
        ]);

        $morphine = ItemMaster::create([
            'tenant_id' => $this->tenant->id,
            'medication_id' => $formulary->id,
            'item_code' => 'MED-MORPH-10MG',
            'name' => 'Morphine 10mg/ml Injection',
            'category' => 'Pharmaceutical',
            'is_dda_narcotic' => true,
            'unit_cost_price' => 4000,
            'unit_selling_price' => 12000,
            'is_active' => true,
        ]);

        $stock = InventoryStockBalance::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'location_id' => $this->wardCabinet->id,
            'medication_id' => $formulary->id,
            'quantity_on_hand' => 5,
        ]);

        $response = $this->actingAs($this->nurse)
            ->post(route('inpatient.admissions.mar.store', $this->admission), [
                'item_master_id' => $morphine->id,
                'item_name' => $morphine->name,
                'location_id' => $this->wardCabinet->id,
                'dose_quantity' => 1,
                'dose_unit' => 'ampoule',
                'route' => 'IV',
                'frequency' => 'STAT',
                'is_dda_narcotic' => true,
                'witness_by' => $this->witnessNurse->id,
                'witness_pin_verified' => true,
                'status' => 'Administered',
                'charge_amount' => 12000,
                'notes' => 'Post-op breakthrough pain 8/10. Witnessed by Nurse Mary.',
            ]);

        $response->assertSessionHasNoErrors();

        // Assert DDA register logged
        $this->assertDatabaseHas('dda_register_logs', [
            'tenant_id' => $this->tenant->id,
            'item_id' => $morphine->id,
            'administering_nurse_id' => $this->nurse->id,
            'witness_user_id' => $this->witnessNurse->id,
            'dose_administered' => 1,
            'balance_before' => 5,
            'balance_after' => 4,
        ]);
    }
}
