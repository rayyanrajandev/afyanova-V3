<?php

namespace Tests\Feature\Domains;

use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Actions\GeneratePredictiveReordersAction;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictiveProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected User $pharmacist;

    protected InventoryLocation $mainStore;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTenantEnvironment();

        $this->pharmacist = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Pharmacist',
            'last_name' => 'James',
        ]);

        $this->mainStore = InventoryLocation::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'name' => 'Central Main Medical Warehouse',
            'code' => 'WH-MAIN-CENTRAL',
            'type' => 'Main_Store',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zenufa Laboratories Tanzania',
            'code' => 'SUP-ZENUFA-01',
            'payment_terms' => 'Net30',
            'is_active' => true,
        ]);
    }

    public function test_predictive_engine_detects_low_stock_and_generates_draft_po(): void
    {
        $formulary = MedicationFormulary::create([
            'tenant_id' => $this->tenant->id,
            'generic_name' => 'Amoxicillin + Clavulanic Acid',
            'brand_name' => 'Augmentin 625mg Tablets',
            'form' => 'Tablet',
            'strength' => '625mg',
            'route' => 'Oral',
            'is_active' => true,
        ]);

        $augmentin = ItemMaster::create([
            'tenant_id' => $this->tenant->id,
            'medication_id' => $formulary->id,
            'item_code' => 'MED-AUG-625MG',
            'name' => 'Augmentin 625mg Tablets',
            'category' => 'Pharmaceutical',
            'safety_stock' => 20,
            'reorder_level' => 50,
            'unit_cost_price' => 1200.00,
            'unit_selling_price' => 2500.00,
            'is_active' => true,
        ]);

        // Low stock: Only 5 tablets left on hand (Safety stock is 20)
        InventoryStockBalance::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'location_id' => $this->mainStore->id,
            'medication_id' => $formulary->id,
            'quantity_on_hand' => 5,
        ]);

        $action = new GeneratePredictiveReordersAction;
        $result = $action->execute($this->tenant->id, $this->facility->id, true);

        $this->assertGreaterThan(0, $result['items_needing_reorder_count']);
        $this->assertNotEmpty($result['purchase_orders_created']);

        $po = $result['purchase_orders_created'][0];
        $this->assertInstanceOf(PurchaseOrder::class, $po);
        $this->assertEquals('Submitted', $po->status);
        $this->assertGreaterThan(0, $po->total_amount);

        $this->assertDatabaseHas('purchase_orders', [
            'tenant_id' => $this->tenant->id,
            'id' => $po->id,
            'status' => 'Submitted',
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'medication_id' => $formulary->id,
        ]);
    }
}
