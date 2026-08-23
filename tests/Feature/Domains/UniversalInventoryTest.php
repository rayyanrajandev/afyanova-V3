<?php

use App\Core\Context\TenantContext;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Actions\ApproveDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\ConfirmDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\CreateDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\IssueDepartmentRequisitionAction;
use App\Domains\Inventory\Actions\RecordDdaAdministrationAction;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MedicalGasCylinder;
use App\Domains\Inventory\Models\UnitOfMeasure;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Models\Department;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Afya Hospital',
        'slug' => 'afya-hospital',
        'domain' => 'afya.local',
        'status' => 'Active',
    ]);
    app(TenantContext::class)->setTenantId($this->tenant->id);

    $this->facility = Facility::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Campus',
        'code' => 'MAIN',
        'is_active' => true,
    ]);

    $this->user = User::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'admin@afya.local',
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'password_hash' => bcrypt('password123'),
        'role' => 'SuperAdmin',
    ]);

    $this->doctor = User::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'doctor@afya.local',
        'first_name' => 'Rajani',
        'last_name' => 'Massawe',
        'password_hash' => bcrypt('password123'),
        'role' => 'Doctor',
    ]);

    $this->nurse = User::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'nurse@afya.local',
        'first_name' => 'Grace',
        'last_name' => 'Mollel',
        'password_hash' => bcrypt('password123'),
        'role' => 'Nurse',
    ]);
});

test('can create universal items across hospital categories with packaging UOM conversion', function () {
    $uomPiece = UnitOfMeasure::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Piece',
        'symbol' => 'pc',
    ]);

    $uomBox = UnitOfMeasure::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Box',
        'symbol' => 'bx',
    ]);

    $cannula = ItemMaster::create([
        'tenant_id' => $this->tenant->id,
        'item_code' => 'MSD-SURG-G20',
        'name' => 'IV Cannula G20 with Port',
        'generic_name' => 'Intravenous Cannula 20G',
        'category' => 'Surgical_Consumable',
        'base_uom_id' => $uomPiece->id,
        'purchasing_uom_id' => $uomBox->id,
        'conversion_ratio' => 100,
        'unit_cost_price' => 800.00,
        'unit_selling_price' => 1500.00,
        'is_billable' => true,
    ]);

    expect($cannula->category)->toBe('Surgical_Consumable');
    expect($cannula->conversion_ratio)->toBe(100);
    expect($cannula->is_billable)->toBeTrue();
});

test('can execute full department store requisition indent workflow (Create -> Approve -> Issue -> Confirm)', function () {
    $centralStore = InventoryLocation::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'code' => 'WH-MAIN-CENTRAL',
        'name' => 'Central Main Store',
        'is_storage_only' => true,
    ]);

    $theatreCabinet = InventoryLocation::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'code' => 'CAB-THEATRE',
        'name' => 'Operating Theatre Cabinet',
        'is_dispensing_enabled' => false,
    ]);

    $theatreDept = Department::create([
        'facility_id' => $this->facility->id,
        'code' => 'DEPT-THEATRE',
        'name' => 'Operating Theatre',
    ]);

    $suture = ItemMaster::create([
        'tenant_id' => $this->tenant->id,
        'item_code' => 'MSD-SUT-VICRYL',
        'name' => 'Suture Vicryl 2/0',
        'category' => 'Surgical_Consumable',
        'unit_cost_price' => 4500.00,
        'unit_selling_price' => 7000.00,
    ]);

    $med = MedicationFormulary::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MED-SUTURE-FORM',
        'generic_name' => 'Vicryl 2/0',
        'brand_name' => 'Ethicon',
        'form' => 'Suture',
        'strength' => '2/0',
        'route' => 'Topical',
    ]);
    $suture->update(['medication_id' => $med->id]);

    $batch = InventoryBatch::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'medication_id' => $med->id,
        'batch_number' => 'BATCH-VICRYL-01',
        'initial_quantity' => 200,
        'current_quantity' => 200,
        'unit_cost' => 4500.00,
        'unit_selling_price' => 7000.00,
        'expiry_date' => now()->addMonths(24)->toDateString(),
        'status' => 'Active',
    ]);

    // Initial stock balance in Central Store
    InventoryStockBalance::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'location_id' => $centralStore->id,
        'medication_id' => $med->id,
        'batch_id' => $batch->id,
        'quantity_on_hand' => 200,
    ]);

    // 1. Ward nurse raises store indent
    $createReqAction = app(CreateDepartmentRequisitionAction::class);
    $req = $createReqAction->execute(
        $this->facility->id,
        $theatreDept->id,
        $centralStore->id,
        $theatreCabinet->id,
        [['item_id' => $suture->id, 'quantity_requested' => 30]],
        'Routine_Weekly',
        $this->nurse->id,
        'Surgical case replenishment for major surgeries'
    );

    expect($req->status)->toBe('Submitted');
    expect($req->items)->toHaveCount(1);
    expect($req->items->first()->quantity_requested)->toBe(30);

    // 2. Matron / HOD approves indent
    $approveReqAction = app(ApproveDepartmentRequisitionAction::class);
    $approvedReq = $approveReqAction->execute(
        $req->id,
        [$req->items->first()->id => 25], // rationed to 25
        $this->doctor->id,
        'Approved 25 units based on weekly theatre schedule'
    );

    expect($approvedReq->status)->toBe('Approved');
    expect($approvedReq->items->first()->quantity_approved)->toBe(25);

    // 3. Central Storekeeper issues and dispatches items
    $issueReqAction = app(IssueDepartmentRequisitionAction::class);
    $dispatchedReq = $issueReqAction->execute(
        $req->id,
        [$req->items->first()->id => ['batch_id' => $batch->id, 'quantity_dispatched' => 25]],
        $this->user->id,
        'Picked from shelf B2'
    );

    expect($dispatchedReq->status)->toBe('Dispatched_In_Transit');

    // Central store balance decremented
    $centralBal = InventoryStockBalance::where('location_id', $centralStore->id)->first();
    expect($centralBal->quantity_on_hand)->toBe(175);

    // 4. Theatre receiving nurse confirms intake into Ward Cabinet
    $confirmReqAction = app(ConfirmDepartmentRequisitionAction::class);
    $confirmedReq = $confirmReqAction->execute(
        $req->id,
        [$req->items->first()->id => 25],
        $this->nurse->id,
        'Verified packaging intact'
    );

    expect($confirmedReq->status)->toBe('Received_Confirmed');

    // Theatre Cabinet balance incremented
    $theatreBal = InventoryStockBalance::where('location_id', $theatreCabinet->id)->first();
    expect($theatreBal)->not->toBeNull();
    expect($theatreBal->quantity_on_hand)->toBe(25);
});

test('can log controlled substance administration in DDA register with dual signatures', function () {
    $morphine = ItemMaster::create([
        'tenant_id' => $this->tenant->id,
        'item_code' => 'MSD-MORPH-10MG',
        'name' => 'Morphine HCl Injection 10mg/ml',
        'generic_name' => 'Morphine Sulfate',
        'category' => 'Pharmaceutical',
        'is_dda_narcotic' => true,
        'unit_cost_price' => 3500.00,
        'unit_selling_price' => 5500.00,
    ]);

    $med = MedicationFormulary::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MED-MORPH-FORM',
        'generic_name' => 'Morphine 10mg/ml',
        'brand_name' => 'Morphine',
        'form' => 'Ampoule',
        'strength' => '10mg/ml',
        'route' => 'Intravenous',
    ]);
    $morphine->update(['medication_id' => $med->id]);

    $batch = InventoryBatch::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'medication_id' => $med->id,
        'batch_number' => 'BATCH-MORPH-01',
        'initial_quantity' => 50,
        'current_quantity' => 50,
        'unit_cost' => 3500.00,
        'unit_selling_price' => 5500.00,
        'expiry_date' => now()->addMonths(18)->toDateString(),
        'status' => 'Active',
    ]);

    $recordDdaAction = app(RecordDdaAdministrationAction::class);
    $log = $recordDdaAction->execute(
        $this->facility->id,
        $morphine->id,
        $batch->id,
        1.0, // 1 ampoule administered (10mg)
        0.0, // 0 wasted
        null,
        null,
        $this->doctor->id,
        $this->nurse->id,
        $this->user->id,
        'Severe post-operative laparotomy analgesia',
        'Administered IV push slowly over 5 minutes'
    );

    expect($log->balance_before)->toBe('50.00');
    expect($log->balance_after)->toBe('49.00');
    expect($log->prescriber_id)->toBe($this->doctor->id);
    expect($log->administering_nurse_id)->toBe($this->nurse->id);

    // Batch current quantity decremented
    $batch->refresh();
    expect($batch->current_quantity)->toBe(49);
});

test('tracks medical oxygen cylinder fleet status transitions', function () {
    $wh = InventoryLocation::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'code' => 'WH-GAS-BANK',
        'name' => 'Central Gas Bank',
    ]);

    $cylinder = MedicalGasCylinder::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'cylinder_serial_number' => 'OXY-TEST-001',
        'gas_type' => 'Oxygen',
        'cylinder_size' => 'Size_J',
        'volume_liters' => 8500,
        'current_location_id' => $wh->id,
        'status' => 'Full_In_Store',
    ]);

    expect($cylinder->status)->toBe('Full_In_Store');

    // Move to ward bed (In-Use)
    $cylinder->update([
        'status' => 'In_Use_Ward',
        'assigned_ward_bed' => 'Maternity Bed 04',
    ]);
    expect($cylinder->fresh()->status)->toBe('In_Use_Ward');

    // Depleted -> Move to Empty Return Bay
    $cylinder->update([
        'status' => 'Empty_Return_Bay',
        'assigned_ward_bed' => null,
    ]);
    expect($cylinder->fresh()->status)->toBe('Empty_Return_Bay');
});

test('catalog search API supports free-text query, category scoping, and exact SKU scan', function () {
    ItemMaster::create([
        'tenant_id' => $this->tenant->id,
        'item_code' => 'MSD-SURG-CANNULA',
        'name' => 'IV Cannula G18 Green',
        'generic_name' => 'Intravenous Cannula 18G',
        'category' => 'Surgical_Consumable',
        'unit_cost_price' => 800.00,
        'unit_selling_price' => 1500.00,
    ]);

    ItemMaster::create([
        'tenant_id' => $this->tenant->id,
        'item_code' => 'MSD-IPC-JIK',
        'name' => 'Sodium Hypochlorite 3.5% Jik',
        'generic_name' => 'Chlorine Disinfectant',
        'category' => 'IPC_Chemical',
        'unit_cost_price' => 35000.00,
        'unit_selling_price' => 45000.00,
    ]);

    // 1. Omnisearch query
    $response = $this->actingAs($this->user)->getJson('/inventory/catalog/search?q=Cannula');
    $response->assertOk()
        ->assertJsonFragment(['item_code' => 'MSD-SURG-CANNULA']);

    // 2. Category-scoped search
    $responseCat = $this->actingAs($this->user)->getJson('/inventory/catalog/search?category=IPC_Chemical');
    $responseCat->assertOk()
        ->assertJsonFragment(['item_code' => 'MSD-IPC-JIK'])
        ->assertJsonMissing(['item_code' => 'MSD-SURG-CANNULA']);

    // 3. Exact SKU Barcode scan
    $responseSku = $this->actingAs($this->user)->getJson('/inventory/catalog/search?sku=MSD-SURG-CANNULA');
    $responseSku->assertOk()
        ->assertJsonFragment(['item_code' => 'MSD-SURG-CANNULA', 'exact_sku_match' => true]);
});
