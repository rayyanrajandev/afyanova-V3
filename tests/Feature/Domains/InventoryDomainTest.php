<?php

use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Actions\ConfirmStockTransferAction;
use App\Domains\Inventory\Actions\CreatePurchaseOrderAction;
use App\Domains\Inventory\Actions\CreateStockTransferAction;
use App\Domains\Inventory\Actions\ProcessGoodsReceiptAction;
use App\Domains\Inventory\Actions\ReconcileStocktakeSessionAction;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\StocktakeSession;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
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
    setTestTenantContext($this->tenant->id);

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
});

test('can create purchase order and receive goods with batch creation and ledger posting', function () {
    $supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'SUP-TEST-01',
        'name' => 'Test Medical Supplies Ltd',
        'payment_terms' => 'Net30',
    ]);

    $wh = InventoryLocation::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'code' => 'WH-TEST-01',
        'name' => 'Test Central Warehouse',
        'is_storage_only' => true,
    ]);

    $med = MedicationFormulary::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MED-TEST-01',
        'generic_name' => 'Amoxicillin 500mg',
        'brand_name' => 'Amoxil',
        'form' => 'Capsule',
        'strength' => '500mg',
        'route' => 'Oral',
    ]);

    // 1. Create PO
    $createPoAction = app(CreatePurchaseOrderAction::class);
    $po = $createPoAction->execute(
        $supplier->id,
        $this->facility->id,
        $wh->id,
        [['medication_id' => $med->id, 'requested_quantity' => 200, 'unit_cost' => 1500.00]],
        now()->toDateString(),
        now()->addDays(7)->toDateString(),
        $this->user->id,
        'Bulk antibacterial PO'
    );

    expect($po->status)->toBe('Submitted');
    expect($po->items)->toHaveCount(1);
    expect((float) $po->total_amount)->toBe(300000.00);

    // 2. Receive GRN with batch creation
    $processGrnAction = app(ProcessGoodsReceiptAction::class);
    $grn = $processGrnAction->execute(
        $po->id,
        $supplier->id,
        $this->facility->id,
        $wh->id,
        [[
            'medication_id' => $med->id,
            'po_item_id' => $po->items->first()->id,
            'batch_number' => 'BATCH-TEST-99',
            'expiry_date' => now()->addMonths(18)->toDateString(),
            'received_quantity' => 200,
            'unit_purchase_cost' => 1500.00,
            'unit_selling_price' => 2200.00,
        ]],
        now()->toDateString(),
        'INV-TEST-001',
        'DN-TEST-001',
        $this->user->id
    );

    expect($grn->status)->toBe('Posted_To_Ledger');
    expect((float) $grn->total_received_value)->toBe(300000.00);

    // Verify stock balance incremented at location
    $balance = InventoryStockBalance::where('location_id', $wh->id)
        ->where('medication_id', $med->id)
        ->first();

    expect($balance)->not->toBeNull();
    expect($balance->quantity_on_hand)->toBeGreaterThanOrEqual(200);

    // Verify PO status completed
    $po->refresh();
    expect($po->status)->toBe('Completed');
});

test('can execute two-step stock transfer handshake between locations', function () {
    $sourceLoc = InventoryLocation::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'code' => 'WH-SRC-01',
        'name' => 'Source Warehouse',
        'is_storage_only' => true,
    ]);

    $destLoc = InventoryLocation::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'code' => 'STORE-DEST-01',
        'name' => 'Dest Pharmacy',
        'is_dispensing_enabled' => true,
    ]);

    $med = MedicationFormulary::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MED-PARAC-01',
        'generic_name' => 'Paracetamol 500mg',
        'brand_name' => 'Panadol',
        'form' => 'Tablet',
        'strength' => '500mg',
        'route' => 'Oral',
    ]);

    $batch = InventoryBatch::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'medication_id' => $med->id,
        'batch_number' => 'BATCH-PARA-01',
        'initial_quantity' => 500,
        'current_quantity' => 500,
        'unit_cost' => 500.00,
        'unit_selling_price' => 750.00,
        'expiry_date' => now()->addMonths(24)->toDateString(),
        'status' => 'Active',
    ]);

    // Seed initial balance at source
    InventoryStockBalance::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'location_id' => $sourceLoc->id,
        'medication_id' => $med->id,
        'batch_id' => $batch->id,
        'quantity_on_hand' => 500,
    ]);

    // 1. Dispatch Transfer
    $createTransferAction = app(CreateStockTransferAction::class);
    $transfer = $createTransferAction->execute(
        $sourceLoc->id,
        $destLoc->id,
        [['medication_id' => $med->id, 'batch_id' => $batch->id, 'quantity' => 150]],
        $this->user->id,
        'Emergency inter-store transfer'
    );

    expect($transfer->status)->toBe('Dispatched_In_Transit');

    // Source balance decremented
    $srcBal = InventoryStockBalance::where('location_id', $sourceLoc->id)
        ->where('batch_id', $batch->id)
        ->first();
    expect($srcBal->quantity_on_hand)->toBe(350);

    // 2. Confirm Transfer Receipt
    $confirmAction = app(ConfirmStockTransferAction::class);
    $confirmedTransfer = $confirmAction->execute(
        $transfer->id,
        null,
        $this->user->id,
        'Received in good condition'
    );

    expect($confirmedTransfer->status)->toBe('Received_Confirmed');

    // Destination balance incremented
    $destBal = InventoryStockBalance::where('location_id', $destLoc->id)
        ->where('batch_id', $batch->id)
        ->first();
    expect($destBal)->not->toBeNull();
    expect($destBal->quantity_on_hand)->toBe(150);
});

test('throws InsufficientStockException on overdraft attempt', function () {
    $sourceLoc = InventoryLocation::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'code' => 'WH-SRC-OD',
        'name' => 'Overdraft WH',
    ]);

    $destLoc = InventoryLocation::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'code' => 'STORE-DEST-OD',
        'name' => 'Dest WH',
    ]);

    $med = MedicationFormulary::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MED-OVERDRAFT',
        'generic_name' => 'Ciprofloxacin 500mg',
        'brand_name' => 'Cipro',
        'form' => 'Tablet',
        'strength' => '500mg',
        'route' => 'Oral',
    ]);

    $batch = InventoryBatch::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'medication_id' => $med->id,
        'batch_number' => 'BATCH-CIPRO-01',
        'initial_quantity' => 20,
        'current_quantity' => 20,
        'unit_cost' => 1200.00,
        'unit_selling_price' => 1800.00,
        'expiry_date' => now()->addMonths(12)->toDateString(),
        'status' => 'Active',
    ]);

    InventoryStockBalance::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'location_id' => $sourceLoc->id,
        'medication_id' => $med->id,
        'batch_id' => $batch->id,
        'quantity_on_hand' => 20,
    ]);

    $createTransferAction = app(CreateStockTransferAction::class);

    // Attempt to transfer 100 when only 20 are available
    $createTransferAction->execute(
        $sourceLoc->id,
        $destLoc->id,
        [['medication_id' => $med->id, 'batch_id' => $batch->id, 'quantity' => 100]],
        $this->user->id
    );
})->throws(InsufficientStockException::class);

test('can reconcile physical stocktake count variance', function () {
    $loc = InventoryLocation::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'code' => 'STORE-STK-01',
        'name' => 'Stocktake Store',
    ]);

    $med = MedicationFormulary::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MED-STK-01',
        'generic_name' => 'Ibuprofen 400mg',
        'brand_name' => 'Brufen',
        'form' => 'Tablet',
        'strength' => '400mg',
        'route' => 'Oral',
    ]);

    $batch = InventoryBatch::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'medication_id' => $med->id,
        'batch_number' => 'BATCH-BRUFEN-01',
        'initial_quantity' => 100,
        'current_quantity' => 100,
        'unit_cost' => 800.00,
        'unit_selling_price' => 1200.00,
        'expiry_date' => now()->addMonths(20)->toDateString(),
        'status' => 'Active',
    ]);

    InventoryStockBalance::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'location_id' => $loc->id,
        'medication_id' => $med->id,
        'batch_id' => $batch->id,
        'quantity_on_hand' => 100,
    ]);

    $session = StocktakeSession::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'location_id' => $loc->id,
        'session_number' => 'STK-TEST-0001',
        'status' => 'In_Progress',
        'initiated_by' => $this->user->id,
    ]);

    $reconcileAction = app(ReconcileStocktakeSessionAction::class);
    // Physical count found 95 (variance of -5)
    $reconciled = $reconcileAction->execute(
        $session->id,
        [[
            'medication_id' => $med->id,
            'batch_id' => $batch->id,
            'physical_counted_quantity' => 95,
            'variance_reason' => 'Minor blister pack breakage',
        ]],
        $this->user->id
    );

    expect($reconciled->status)->toBe('Approved_Reconciled');
    expect($reconciled->items)->toHaveCount(1);
    expect($reconciled->items->first()->variance_quantity)->toBe(-5);

    // Balance updated to 95
    $balance = InventoryStockBalance::where('location_id', $loc->id)
        ->where('batch_id', $batch->id)
        ->first();
    expect($balance->quantity_on_hand)->toBe(95);
});
