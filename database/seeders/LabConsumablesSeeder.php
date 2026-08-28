<?php

namespace Database\Seeders;

use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\UnitOfMeasure;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;

class LabConsumablesSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            return;
        }

        foreach ($tenants as $tenant) {
            $facility = Facility::where('tenant_id', $tenant->id)->first();
            if (! $facility) {
                continue;
            }

            $uomPc = UnitOfMeasure::firstOrCreate(
                ['tenant_id' => $tenant->id, 'symbol' => 'PC'],
                ['name' => 'Piece / Unit', 'description' => 'Discrete unit']
            );
            $uomBox = UnitOfMeasure::firstOrCreate(
                ['tenant_id' => $tenant->id, 'symbol' => 'BOX-100'],
                ['name' => 'Box of 100 Pieces', 'description' => 'Packaging box']
            );

            $labStore = InventoryLocation::firstOrCreate(
                ['code' => 'LOC-LAB-PHLEB-'.substr($tenant->id, 0, 8)],
                [
                    'tenant_id' => $tenant->id,
                    'facility_id' => $facility->id,
                    'name' => 'Laboratory Phlebotomy Bench Store',
                    'type' => 'DepartmentSubStore',
                    'is_dispensing_enabled' => true,
                    'description' => 'Working store for blood collection vacutainer tubes and sample cups.',
                ]
            );

            $consumables = [
                [
                    'code' => 'MSD-LAB-EDTA-01',
                    'name' => 'Vacutainer EDTA K2/K3 Purple Top Tube 4ml',
                    'generic' => 'Blood Collection Tube (EDTA K2/K3)',
                    'category' => 'Laboratory Consumables',
                    'cost' => 300.00,
                    'price' => 500.00,
                ],
                [
                    'code' => 'MSD-LAB-SST-01',
                    'name' => 'Vacutainer SST Gel Separator Gold Top Tube 5ml',
                    'generic' => 'Blood Collection Tube (Serum Clot / Gel)',
                    'category' => 'Laboratory Consumables',
                    'cost' => 350.00,
                    'price' => 600.00,
                ],
                [
                    'code' => 'MSD-LAB-RED-01',
                    'name' => 'Vacutainer Plain Red Top Clot Activator Tube 5ml',
                    'generic' => 'Blood Collection Tube (Plain Red)',
                    'category' => 'Laboratory Consumables',
                    'cost' => 280.00,
                    'price' => 500.00,
                ],
                [
                    'code' => 'MSD-LAB-CIT-01',
                    'name' => 'Vacutainer Sodium Citrate 3.2% Light Blue Top Tube 2.7ml',
                    'generic' => 'Blood Collection Tube (Sodium Citrate 3.2%)',
                    'category' => 'Laboratory Consumables',
                    'cost' => 400.00,
                    'price' => 650.00,
                ],
                [
                    'code' => 'MSD-LAB-GLU-01',
                    'name' => 'Vacutainer Sodium Fluoride / Potassium Oxalate Grey Top Tube 2ml',
                    'generic' => 'Blood Collection Tube (Fluoride Oxalate)',
                    'category' => 'Laboratory Consumables',
                    'cost' => 320.00,
                    'price' => 550.00,
                ],
                [
                    'code' => 'MSD-LAB-URI-01',
                    'name' => 'Sterile Urine Specimen Container 60ml Yellow Cap',
                    'generic' => 'Specimen Container (Urine)',
                    'category' => 'Laboratory Consumables',
                    'cost' => 450.00,
                    'price' => 800.00,
                ],
                [
                    'code' => 'MSD-LAB-STL-01',
                    'name' => 'Stool Specimen Container with Scoop/Spoon 30ml',
                    'generic' => 'Specimen Container (Stool)',
                    'category' => 'Laboratory Consumables',
                    'cost' => 450.00,
                    'price' => 800.00,
                ],
                [
                    'code' => 'MSD-LAB-SPT-01',
                    'name' => 'Sputum Specimen Container with Screw Cap 50ml',
                    'generic' => 'Specimen Container (Sputum)',
                    'category' => 'Laboratory Consumables',
                    'cost' => 500.00,
                    'price' => 900.00,
                ],
                [
                    'code' => 'MSD-LAB-SWB-01',
                    'name' => 'Sterile Cotton / Dacron Swab with Transport Tube',
                    'generic' => 'Microbiology Specimen Swab',
                    'category' => 'Laboratory Consumables',
                    'cost' => 350.00,
                    'price' => 600.00,
                ],
            ];

            foreach ($consumables as $c) {
                $med = MedicationFormulary::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'generic_name' => $c['generic'], 'strength' => 'Standard'],
                    [
                        'brand_name' => $c['name'],
                        'form' => 'Consumable',
                        'route' => 'External',
                        'drug_class' => 'Laboratory Consumables',
                        'is_active' => true,
                    ]
                );

                $item = ItemMaster::firstOrCreate(
                    ['item_code' => $c['code']],
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $c['name'],
                        'generic_name' => $c['generic'],
                        'category' => $c['category'],
                        'base_uom_id' => $uomPc->id,
                        'purchasing_uom_id' => $uomBox->id,
                        'conversion_ratio' => 100,
                        'unit_cost_price' => $c['cost'],
                        'unit_selling_price' => $c['price'],
                        'is_billable' => false,
                        'is_dda_narcotic' => false,
                        'medication_id' => $med->id,
                    ]
                );

                $batch = InventoryBatch::firstOrCreate(
                    ['batch_number' => 'BATCH-'.$c['code'].'-'.substr($facility->id, 0, 6)],
                    [
                        'tenant_id' => $tenant->id,
                        'facility_id' => $facility->id,
                        'medication_id' => $med->id,
                        'initial_quantity' => 1000,
                        'current_quantity' => 1000,
                        'unit_cost' => $c['cost'],
                        'unit_selling_price' => $c['price'],
                        'expiry_date' => now()->addMonths(36)->toDateString(),
                        'status' => 'Active',
                    ]
                );

                InventoryStockBalance::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'facility_id' => $facility->id,
                        'location_id' => $labStore->id,
                        'medication_id' => $med->id,
                        'batch_id' => $batch->id,
                    ],
                    [
                        'quantity_on_hand' => 1000,
                        'reorder_level' => 100,
                        'reorder_quantity' => 500,
                    ]
                );
            }
        }
    }
}
