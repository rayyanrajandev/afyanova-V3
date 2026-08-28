<?php

namespace Database\Seeders;

use App\Core\Context\TenantContext;
use App\Domains\Billing\Models\CashierShift;
use App\Domains\Billing\Models\ChargeMasterItem;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Insurance\Models\InsuranceProvider;
use App\Domains\Insurance\Models\InsuranceScheme;
use App\Domains\Inventory\Models\DepartmentRequisition;
use App\Domains\Inventory\Models\DepartmentRequisitionItem;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\MedicalGasCylinder;
use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\StockTransferItem;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Inventory\Models\UnitOfMeasure;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;
use App\Domains\Procedure\Models\OperatingSuite;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Tenancy\Models\Department;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            abort(403, 'Refusing to run demo/seed data against a production environment.');
        }

        // 1. Create Tenant (or fetch existing)
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'dar-medical'],
            [
                'name' => 'Dar es Salaam Medical Center',
                'domain' => 'dar-medical.local',
                'status' => 'active',
            ]
        );

        // Mock Tenant Context. Console commands never pass through
        // TenantContextMiddleware, so — unlike a real request — the
        // Postgres session variable RLS policies key off has to be set
        // explicitly here too, or every insert below is rejected by the
        // FORCE ROW LEVEL SECURITY policies now that they're enforced even
        // for the owning role.
        $context = app(TenantContext::class);
        $context->setTenantId($tenant->id);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $tenant->id]);
        }

        // 2. Create Facility
        $facility = Facility::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'HQ-01'],
            [
                'name' => 'Main Campus',
                'is_active' => true,
            ]
        );

        // 3. Create Roles
        $doctorRole = Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'doctor'],
            ['name' => 'Doctor', 'description' => 'Physician Role']
        );

        $cashierRole = Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'cashier'],
            ['name' => 'Cashier', 'description' => 'Billing Role']
        );

        // 4. Create Users
        $doctor = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'doctor@afyanova.local'],
            [
                'first_name' => 'Rajani',
                'last_name' => 'Massawe',
                'password_hash' => Hash::make('password123'),
                'status' => 'active',
            ]
        );

        DB::table('role_assignments')->updateOrInsert(
            ['user_id' => $doctor->id, 'role_id' => $doctorRole->id],
            ['id' => Uuid::uuid7()->toString(), 'created_at' => now(), 'updated_at' => now()]
        );

        $cashier = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'cashier@afyanova.local'],
            [
                'first_name' => 'Grace',
                'last_name' => 'Mollel',
                'password_hash' => Hash::make('password123'),
                'status' => 'active',
            ]
        );

        DB::table('role_assignments')->updateOrInsert(
            ['user_id' => $cashier->id, 'role_id' => $cashierRole->id],
            ['id' => Uuid::uuid7()->toString(), 'created_at' => now(), 'updated_at' => now()]
        );

        // 5. Create Medication Formularies & Charge Master Items
        $medications = [
            ['generic_name' => 'Amoxicillin', 'brand_name' => 'Amoxil', 'form' => 'Capsule', 'strength' => '500mg', 'route' => 'PO', 'drug_class' => 'Penicillin', 'charge_code' => 'PHARM-AMOXICILLIN-500', 'unit_price' => 300.00],
            ['generic_name' => 'Paracetamol', 'brand_name' => 'Panadol', 'form' => 'Tablet', 'strength' => '500mg', 'route' => 'PO', 'drug_class' => 'Analgesic', 'charge_code' => 'PHARM-PARACETAMOL-500', 'unit_price' => 150.00],
            ['generic_name' => 'Ibuprofen', 'brand_name' => 'Brufen', 'form' => 'Tablet', 'strength' => '400mg', 'route' => 'PO', 'drug_class' => 'NSAID', 'charge_code' => 'PHARM-IBUPROFEN-400', 'unit_price' => 200.00],
            ['generic_name' => 'Artemether / Lumefantrine', 'brand_name' => 'Coartem', 'form' => 'Tablet', 'strength' => '20/120mg', 'route' => 'PO', 'drug_class' => 'Antimalarial', 'charge_code' => 'PHARM-ALU-20-120', 'unit_price' => 2500.00],
            ['generic_name' => 'Omeprazole', 'brand_name' => 'Prilosec', 'form' => 'Capsule', 'strength' => '20mg', 'route' => 'PO', 'drug_class' => 'Proton Pump Inhibitor', 'charge_code' => 'PHARM-OMEPRAZOLE-20', 'unit_price' => 400.00],
            ['generic_name' => 'Azithromycin', 'brand_name' => 'Zithromax', 'form' => 'Tablet', 'strength' => '500mg', 'route' => 'PO', 'drug_class' => 'Macrolide', 'charge_code' => 'PHARM-AZITHROMYCIN-500', 'unit_price' => 1200.00],
            ['generic_name' => 'Metformin', 'brand_name' => 'Glucophage', 'form' => 'Tablet', 'strength' => '500mg', 'route' => 'PO', 'drug_class' => 'Biguanide', 'charge_code' => 'PHARM-METFORMIN-500', 'unit_price' => 250.00],
            ['generic_name' => 'Amlodipine', 'brand_name' => 'Norvasc', 'form' => 'Tablet', 'strength' => '5mg', 'route' => 'PO', 'drug_class' => 'Calcium Channel Blocker', 'charge_code' => 'PHARM-AMLODIPINE-5', 'unit_price' => 350.00],
        ];

        foreach ($medications as $med) {
            $unitPrice = $med['unit_price'];
            $formularyAttrs = collect($med)->except('unit_price')->all();

            MedicationFormulary::firstOrCreate(
                ['tenant_id' => $tenant->id, 'generic_name' => $formularyAttrs['generic_name'], 'strength' => $formularyAttrs['strength']],
                array_merge($formularyAttrs, ['tenant_id' => $tenant->id, 'is_active' => true])
            );

            ChargeMasterItem::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $med['charge_code']],
                [
                    'name' => "{$med['generic_name']} {$med['strength']}",
                    'category' => 'Pharmacy',
                    'unit_price' => $unitPrice,
                    'currency' => 'TZS',
                    'effective_from' => now()->subYear()->toDateString(),
                    'is_active' => true,
                ]
            );
        }

        ChargeMasterItem::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'CONSULT-OPD'],
            [
                'name' => 'General OPD Consultation',
                'category' => 'Consultation',
                'unit_price' => 20000.00,
                'currency' => 'TZS',
                'effective_from' => now()->subYear()->toDateString(),
                'is_active' => true,
            ]
        );

        // 9. Create Active Cashier Shift Session
        $today = now()->format('Ymd');
        CashierShift::firstOrCreate(
            ['tenant_id' => $tenant->id, 'shift_number' => "SHIFT-{$today}-001"],
            [
                'facility_id' => $facility->id,
                'user_id' => $doctor->id,
                'status' => 'Open',
                'opened_at' => now()->subHours(3),
                'opening_float' => 50000.00,
                'expected_cash_total' => 50000.00,
                'variance_status' => 'Pending',
                'notes' => 'Main OPD Cashier Counter 1 - Morning Shift',
            ]
        );

        // 10. Seed Standard Diagnostic Lab Test Catalog
        $labTests = [
            [
                'test_code' => 'LAB-MAL-001',
                'name' => 'Malaria Rapid Diagnostic Test (mRDT)',
                'category' => 'Parasitology',
                'specimen_type' => 'Capillary Whole Blood',
                'turnaround_time_minutes' => 15,
                'price' => 5000.00,
                'parameters' => [
                    ['name' => 'Malaria Antigen (P. falciparum/Pan)', 'type' => 'qualitative', 'normal' => 'Negative', 'critical_value' => 'Positive (High Density)'],
                ],
            ],
            [
                'test_code' => 'LAB-FBP-001',
                'name' => 'Full Blood Picture (FBP / CBC)',
                'category' => 'Hematology',
                'specimen_type' => 'Whole Blood (EDTA)',
                'turnaround_time_minutes' => 30,
                'price' => 15000.00,
                'parameters' => [
                    ['name' => 'Hemoglobin (Hb)', 'unit' => 'g/dL', 'min' => 12.0, 'max' => 17.5, 'panic_low' => 7.0, 'panic_high' => 20.0],
                    ['name' => 'White Blood Cells (WBC)', 'unit' => 'x10^9/L', 'min' => 4.0, 'max' => 11.0, 'panic_low' => 2.0, 'panic_high' => 30.0],
                    ['name' => 'Platelets', 'unit' => 'x10^9/L', 'min' => 150, 'max' => 450, 'panic_low' => 50, 'panic_high' => 1000],
                    ['name' => 'Hematocrit (HCT)', 'unit' => '%', 'min' => 36.0, 'max' => 50.0, 'panic_low' => 20.0, 'panic_high' => 60.0],
                ],
            ],
            [
                'test_code' => 'LAB-GLU-001',
                'name' => 'Random Blood Glucose (RBG)',
                'category' => 'Clinical Chemistry',
                'specimen_type' => 'Capillary Blood / Serum',
                'turnaround_time_minutes' => 10,
                'price' => 4000.00,
                'parameters' => [
                    ['name' => 'Blood Glucose', 'unit' => 'mmol/L', 'min' => 3.9, 'max' => 7.8, 'panic_low' => 2.5, 'panic_high' => 20.0],
                ],
            ],
            [
                'test_code' => 'LAB-RFT-001',
                'name' => 'Renal Function Test (Creatinine & Urea)',
                'category' => 'Clinical Chemistry',
                'specimen_type' => 'Serum',
                'turnaround_time_minutes' => 45,
                'price' => 18000.00,
                'parameters' => [
                    ['name' => 'Serum Creatinine', 'unit' => 'µmol/L', 'min' => 53, 'max' => 115, 'panic_low' => null, 'panic_high' => 300],
                    ['name' => 'Blood Urea Nitrogen (BUN)', 'unit' => 'mmol/L', 'min' => 2.5, 'max' => 7.1, 'panic_low' => null, 'panic_high' => 25.0],
                ],
            ],
            [
                'test_code' => 'LAB-URI-001',
                'name' => 'Urinalysis Routine & Microscopy',
                'category' => 'Urinalysis',
                'specimen_type' => 'Clean Catch Midstream Urine',
                'turnaround_time_minutes' => 20,
                'price' => 8000.00,
                'parameters' => [
                    ['name' => 'Protein', 'type' => 'qualitative', 'normal' => 'Negative', 'critical_value' => '3+ (Massive Proteinuria)'],
                    ['name' => 'Leukocyte Esterase', 'type' => 'qualitative', 'normal' => 'Negative', 'critical_value' => null],
                    ['name' => 'Nitrite', 'type' => 'qualitative', 'normal' => 'Negative', 'critical_value' => null],
                    ['name' => 'Urine pH', 'unit' => 'pH', 'min' => 4.5, 'max' => 8.0, 'panic_low' => null, 'panic_high' => null],
                ],
            ],
            [
                'test_code' => 'LAB-LIP-001',
                'name' => 'Lipid Profile Panel',
                'category' => 'Clinical Chemistry',
                'specimen_type' => 'Serum (Fasting)',
                'turnaround_time_minutes' => 60,
                'price' => 25000.00,
                'parameters' => [
                    ['name' => 'Total Cholesterol', 'unit' => 'mmol/L', 'min' => 3.0, 'max' => 5.2, 'panic_low' => null, 'panic_high' => 10.0],
                    ['name' => 'HDL Cholesterol', 'unit' => 'mmol/L', 'min' => 1.0, 'max' => 2.2, 'panic_low' => null, 'panic_high' => null],
                    ['name' => 'LDL Cholesterol', 'unit' => 'mmol/L', 'min' => 1.5, 'max' => 3.4, 'panic_low' => null, 'panic_high' => 8.0],
                    ['name' => 'Triglycerides', 'unit' => 'mmol/L', 'min' => 0.5, 'max' => 1.7, 'panic_low' => null, 'panic_high' => 11.0],
                ],
            ],
        ];

        foreach ($labTests as $lt) {
            LabTest::firstOrCreate(
                ['tenant_id' => $tenant->id, 'test_code' => $lt['test_code']],
                [
                    'name' => $lt['name'],
                    'category' => $lt['category'],
                    'specimen_type' => $lt['specimen_type'],
                    'turnaround_time_minutes' => $lt['turnaround_time_minutes'],
                    'price' => $lt['price'],
                    'parameters' => $lt['parameters'],
                    'is_active' => true,
                ]
            );
        }

        // 12. Create Realistic FEFO Inventory Batches & Stock Movements
        $allMeds = MedicationFormulary::where('tenant_id', $tenant->id)->get();
        foreach ($allMeds as $med) {
            // Batch 1 (Primary FEFO batch - expires earlier)
            $batch1 = InventoryBatch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'medication_id' => $med->id, 'batch_number' => 'MSD-2026-'.strtoupper(substr(md5($med->id), 0, 4))],
                [
                    'facility_id' => $facility->id,
                    'expiry_date' => now()->addMonths(6)->toDateString(),
                    'manufacture_date' => now()->subMonths(6)->toDateString(),
                    'initial_quantity' => 500,
                    'current_quantity' => 500,
                    'unit_cost' => 350.00,
                    'unit_selling_price' => 500.00,
                    'supplier_name' => 'MSD (Medical Stores Department)',
                    'status' => 'Active',
                    'notes' => 'Central medical stores standard quarterly supply',
                ]
            );

            StockMovement::firstOrCreate(
                ['tenant_id' => $tenant->id, 'batch_id' => $batch1->id, 'reference_type' => 'GoodsReceipt'],
                [
                    'facility_id' => $facility->id,
                    'medication_id' => $med->id,
                    'movement_type' => 'Received',
                    'quantity_change' => 500,
                    'quantity_before' => 0,
                    'quantity_after' => 500,
                    'performed_by' => $doctor->id,
                    'notes' => 'Initial bulk intake from MSD Tanzania',
                ]
            );

            // Batch 2 (Secondary batch - expires later)
            $batch2 = InventoryBatch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'medication_id' => $med->id, 'batch_number' => 'ZEN-2027-'.strtoupper(substr(md5($med->id.'b2'), 0, 4))],
                [
                    'facility_id' => $facility->id,
                    'expiry_date' => now()->addMonths(18)->toDateString(),
                    'manufacture_date' => now()->subMonths(1)->toDateString(),
                    'initial_quantity' => 800,
                    'current_quantity' => 800,
                    'unit_cost' => 400.00,
                    'unit_selling_price' => 600.00,
                    'supplier_name' => 'Zenufa Laboratories Ltd (Tanzania)',
                    'status' => 'Active',
                    'notes' => 'Direct local manufacturer consignment',
                ]
            );

            StockMovement::firstOrCreate(
                ['tenant_id' => $tenant->id, 'batch_id' => $batch2->id, 'reference_type' => 'GoodsReceipt'],
                [
                    'facility_id' => $facility->id,
                    'medication_id' => $med->id,
                    'movement_type' => 'Received',
                    'quantity_change' => 800,
                    'quantity_before' => 500,
                    'quantity_after' => 1300,
                    'performed_by' => $doctor->id,
                    'notes' => 'Secondary intake from Zenufa Laboratories',
                ]
            );
        }

        // 13. Create Realistic Inpatient Wards, Beds, and Active Census Admissions
        $wardsData = [
            [
                'name' => 'Male Medical Ward (MMW)',
                'code' => 'MMW-01',
                'ward_type' => 'General',
                'gender_restriction' => 'MaleOnly',
                'floor_location' => 'Ground Floor - Wing A',
                'daily_base_rate' => 25000.00,
                'beds' => ['MMW-BED-01', 'MMW-BED-02', 'MMW-BED-03', 'MMW-BED-04'],
                'bed_types' => ['Standard', 'Standard', 'Oxygen_Equipped', 'Standard'],
            ],
            [
                'name' => 'Female Surgical Ward (FSW)',
                'code' => 'FSW-01',
                'ward_type' => 'Surgical',
                'gender_restriction' => 'FemaleOnly',
                'floor_location' => '1st Floor - Wing B',
                'daily_base_rate' => 35000.00,
                'beds' => ['FSW-BED-01', 'FSW-BED-02', 'FSW-BED-03', 'FSW-BED-04'],
                'bed_types' => ['Standard', 'Oxygen_Equipped', 'Standard', 'VIP_Suite'],
            ],
            [
                'name' => 'Maternity & Neonatal Wing',
                'code' => 'MAT-01',
                'ward_type' => 'Maternity',
                'gender_restriction' => 'FemaleOnly',
                'floor_location' => 'Ground Floor - Wing C',
                'daily_base_rate' => 30000.00,
                'beds' => ['MAT-BED-01', 'MAT-BED-02', 'MAT-BED-03', 'MAT-BED-04'],
                'bed_types' => ['Standard', 'Bassinet', 'Standard', 'VIP_Suite'],
            ],
            [
                'name' => 'Intensive Care Unit (ICU)',
                'code' => 'ICU-01',
                'ward_type' => 'ICU',
                'gender_restriction' => 'Mixed',
                'floor_location' => '2nd Floor - Critical Care',
                'daily_base_rate' => 80000.00,
                'beds' => ['ICU-BED-01', 'ICU-BED-02', 'ICU-BED-03', 'ICU-BED-04'],
                'bed_types' => ['ICU_Electric', 'ICU_Electric', 'ICU_Electric', 'ICU_Electric'],
            ],
            [
                'name' => 'Pediatric & Child Health Ward',
                'code' => 'PED-01',
                'ward_type' => 'Pediatric',
                'gender_restriction' => 'Pediatric',
                'floor_location' => '1st Floor - Wing A',
                'daily_base_rate' => 20000.00,
                'beds' => ['PED-BED-01', 'PED-BED-02', 'PED-BED-03', 'PED-BED-04'],
                'bed_types' => ['Bassinet', 'Standard', 'Oxygen_Equipped', 'Standard'],
            ],
        ];

        $allCreatedBeds = [];

        foreach ($wardsData as $wData) {
            $ward = Ward::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $wData['code']],
                [
                    'facility_id' => $facility->id,
                    'name' => $wData['name'],
                    'ward_type' => $wData['ward_type'],
                    'gender_restriction' => $wData['gender_restriction'],
                    'floor_location' => $wData['floor_location'],
                    'daily_base_rate' => $wData['daily_base_rate'],
                    'is_active' => true,
                    'description' => "Standard inpatient clinical ward at {$facility->name}",
                ]
            );

            foreach ($wData['beds'] as $idx => $bedNum) {
                $bedType = $wData['bed_types'][$idx] ?? 'Standard';
                $dailyRate = $wData['daily_base_rate'];
                if ($bedType === 'ICU_Electric') {
                    $dailyRate = 80000.00;
                }
                if ($bedType === 'VIP_Suite') {
                    $dailyRate = 60000.00;
                }

                $bed = Bed::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'ward_id' => $ward->id, 'bed_number' => $bedNum],
                    [
                        'facility_id' => $facility->id,
                        'bed_type' => $bedType,
                        'daily_rate_amount' => $dailyRate,
                        'status' => 'Available',
                    ]
                );

                $allCreatedBeds[$bedNum] = $bed;
            }
        }

        // Seed 1 Bed in Cleaning state
        $icuBed1 = $allCreatedBeds['ICU-BED-01'] ?? null;
        if ($icuBed1) {
            $icuBed1->update(['status' => 'Cleaning']);
        }

        // 14. Seed Health Insurance Providers, Schemes, Policies, Pre-Auths & Claims
        $nhif = InsuranceProvider::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'NHIF'],
            [
                'name' => 'National Health Insurance Fund (NHIF)',
                'provider_type' => 'NationalScheme',
                'api_adapter' => 'nhif_adapter',
                'contact_email' => 'claims@nhif.or.tz',
                'contact_phone' => '+255 22 211 0000',
                'is_active' => true,
            ]
        );

        $jubilee = InsuranceProvider::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'JUBILEE'],
            [
                'name' => 'Jubilee Insurance Tanzania',
                'provider_type' => 'PrivateHMO',
                'api_adapter' => 'jubilee_adapter',
                'contact_email' => 'medicalclaims@jubileetanzania.com',
                'contact_phone' => '+255 22 213 5121',
                'is_active' => true,
            ]
        );

        $strategis = InsuranceProvider::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'STRATEGIS'],
            [
                'name' => 'Strategis Insurance Tanzania',
                'provider_type' => 'PrivateHMO',
                'api_adapter' => 'generic_adapter',
                'contact_email' => 'claims@strategistz.com',
                'contact_phone' => '+255 22 260 0380',
                'is_active' => true,
            ]
        );

        $aar = InsuranceProvider::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'AAR'],
            [
                'name' => 'AAR Insurance Tanzania',
                'provider_type' => 'PrivateHMO',
                'api_adapter' => 'generic_adapter',
                'contact_email' => 'info@aar.co.tz',
                'contact_phone' => '+255 22 270 1121',
                'is_active' => true,
            ]
        );

        // Schemes
        $nhifScheme1 = InsuranceScheme::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'NHIF-STD'],
            [
                'insurance_provider_id' => $nhif->id,
                'name' => 'NHIF Formal Sector & Civil Servants',
                'co_pay_type' => 'None',
                'co_pay_amount' => 0.00,
                'annual_limit_amount' => 10000000.00,
                'requires_pre_auth' => false,
                'is_active' => true,
            ]
        );

        $jubileeScheme1 = InsuranceScheme::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'JUB-GOLD'],
            [
                'insurance_provider_id' => $jubilee->id,
                'name' => 'Jubilee Corporate Gold Scheme',
                'co_pay_type' => 'None',
                'co_pay_amount' => 0.00,
                'annual_limit_amount' => 25000000.00,
                'requires_pre_auth' => true,
                'is_active' => true,
            ]
        );

        $strategisScheme1 = InsuranceScheme::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'STR-PREM'],
            [
                'insurance_provider_id' => $strategis->id,
                'name' => 'Strategis Premier Executive',
                'co_pay_type' => 'FixedAmount',
                'co_pay_amount' => 5000.00,
                'annual_limit_amount' => 15000000.00,
                'requires_pre_auth' => true,
                'is_active' => true,
            ]
        );

        // ----------------------------------------------------
        // SECTION 15: PROCEDURES & OPERATING THEATRE
        // ----------------------------------------------------
        $procInj = ProcedureCatalog::firstOrCreate(
            ['tenant_id' => $tenant->id, 'procedure_code' => 'PROC-INJ-001'],
            [
                'name' => 'Intramuscular / Intravenous Injection Administration',
                'category' => 'Injection',
                'tier_level' => 'Tier1_Minor',
                'default_duration_minutes' => 10,
                'standard_price' => 2000.00,
                'requires_consent' => false,
                'requires_anesthesia' => false,
                'is_active' => true,
            ]
        );

        $proc1 = ProcedureCatalog::firstOrCreate(
            ['tenant_id' => $tenant->id, 'procedure_code' => 'PROC-DRS-001'],
            [
                'name' => 'Wound Debridement & Sterile Dressing',
                'category' => 'Dressing',
                'tier_level' => 'Tier1_Minor',
                'default_duration_minutes' => 20,
                'standard_price' => 15000.00,
                'requires_consent' => false,
                'requires_anesthesia' => false,
                'is_active' => true,
            ]
        );

        $proc2 = ProcedureCatalog::firstOrCreate(
            ['tenant_id' => $tenant->id, 'procedure_code' => 'PROC-SUT-001'],
            [
                'name' => 'Laceration Suturing (< 5cm)',
                'category' => 'MinorSurgery',
                'tier_level' => 'Tier1_Minor',
                'default_duration_minutes' => 30,
                'standard_price' => 25000.00,
                'requires_consent' => true,
                'requires_anesthesia' => true,
                'is_active' => true,
            ]
        );

        $proc3 = ProcedureCatalog::firstOrCreate(
            ['tenant_id' => $tenant->id, 'procedure_code' => 'PROC-IND-001'],
            [
                'name' => 'Incision & Drainage of Abscess (I&D)',
                'category' => 'MinorSurgery',
                'tier_level' => 'Tier1_Minor',
                'default_duration_minutes' => 25,
                'standard_price' => 35000.00,
                'requires_consent' => true,
                'requires_anesthesia' => true,
                'is_active' => true,
            ]
        );

        $proc4 = ProcedureCatalog::firstOrCreate(
            ['tenant_id' => $tenant->id, 'procedure_code' => 'SURG-CS-001'],
            [
                'name' => 'Emergency Caesarean Section (Lower Segment)',
                'category' => 'OBGYN',
                'tier_level' => 'Tier2_MajorTheatre',
                'default_duration_minutes' => 60,
                'standard_price' => 450000.00,
                'requires_consent' => true,
                'requires_anesthesia' => true,
                'is_active' => true,
            ]
        );

        $proc5 = ProcedureCatalog::firstOrCreate(
            ['tenant_id' => $tenant->id, 'procedure_code' => 'SURG-APP-001'],
            [
                'name' => 'Open Emergency Appendectomy',
                'category' => 'MajorSurgery',
                'tier_level' => 'Tier2_MajorTheatre',
                'default_duration_minutes' => 75,
                'standard_price' => 350000.00,
                'requires_consent' => true,
                'requires_anesthesia' => true,
                'is_active' => true,
            ]
        );

        // Operating Suites
        $suite1 = OperatingSuite::firstOrCreate(
            ['tenant_id' => $tenant->id, 'suite_code' => 'OT-SUITE-1'],
            [
                'facility_id' => $facility->id,
                'name' => 'Major Operating Suite 1',
                'suite_type' => 'Major',
                'status' => 'Occupied',
                'is_active' => true,
            ]
        );

        $suite2 = OperatingSuite::firstOrCreate(
            ['tenant_id' => $tenant->id, 'suite_code' => 'OT-SUITE-2'],
            [
                'facility_id' => $facility->id,
                'name' => 'Minor Procedure Theatre 2',
                'suite_type' => 'Minor',
                'status' => 'Available',
                'is_active' => true,
            ]
        );

        $suite3 = OperatingSuite::firstOrCreate(
            ['tenant_id' => $tenant->id, 'suite_code' => 'OT-MAT-1'],
            [
                'facility_id' => $facility->id,
                'name' => 'Maternity & Obstetric OT',
                'suite_type' => 'Obstetric',
                'status' => 'Available',
                'is_active' => true,
            ]
        );

        // Seed fast-moving dispensed stock movements & near-expiry batch for inventory telemetry
        $primaryDoctor = User::where('tenant_id', $tenant->id)->where('email', 'doctor@afyanova.local')->first() ?? $doctor;
        $batches = InventoryBatch::with('medication')->where('tenant_id', $tenant->id)->get();
        foreach ($batches->take(6) as $idx => $b) {
            StockMovement::create([
                'tenant_id' => $tenant->id,
                'facility_id' => $facility->id,
                'medication_id' => $b->medication_id,
                'batch_id' => $b->id,
                'movement_type' => 'Dispensed',
                'quantity_change' => -(25 + ($idx * 15)),
                'quantity_before' => $b->current_quantity + (25 + ($idx * 15)),
                'quantity_after' => $b->current_quantity,
                'reference_type' => 'Dispensation',
                'reference_id' => $b->id,
                'notes' => 'Routine Pharmacy Dispensation',
                'performed_by' => $primaryDoctor->id,
                'created_at' => now()->subDays(rand(1, 10)),
            ]);
        }

        // Seed a near-expiry critical batch (< 30 days)
        if ($batches->isNotEmpty()) {
            $nearExpiryBatch = $batches->first();
            $nearExpiryBatch->update([
                'expiry_date' => now()->addDays(18),
            ]);
        }

        // ==============================================================
        // 17. SECTION 17: FULL MULTI-DIMENSIONAL RBAC & ROLES
        // ==============================================================
        $permissionsList = [
            // Clinical
            ['name' => 'Create Encounter', 'slug' => 'clinical.encounter.create', 'domain' => 'Clinical'],
            ['name' => 'View Encounter', 'slug' => 'clinical.encounter.view', 'domain' => 'Clinical'],
            ['name' => 'Update Encounter', 'slug' => 'clinical.encounter.update', 'domain' => 'Clinical'],
            ['name' => 'Close Encounter', 'slug' => 'clinical.encounter.close', 'domain' => 'Clinical'],
            ['name' => 'Sign Clinical Notes', 'slug' => 'clinical.notes.sign', 'domain' => 'Clinical'],
            ['name' => 'Record Vital Signs', 'slug' => 'clinical.vitals.record', 'domain' => 'Clinical'],
            ['name' => 'Manage Diagnoses', 'slug' => 'clinical.diagnosis.manage', 'domain' => 'Clinical'],
            ['name' => 'Record Informed Consent', 'slug' => 'clinical.consent.record', 'domain' => 'Clinical'],
            ['name' => 'Create Inter-Facility Referral', 'slug' => 'clinical.referral.create', 'domain' => 'Clinical'],
            ['name' => 'Administer Immunization', 'slug' => 'clinical.immunization.administer', 'domain' => 'Clinical'],
            ['name' => 'Record ANC Visit', 'slug' => 'clinical.anc.record', 'domain' => 'Clinical'],
            ['name' => 'Record Partograph Entry', 'slug' => 'clinical.partograph.record', 'domain' => 'Clinical'],
            ['name' => 'Manage Problem List', 'slug' => 'clinical.problem-list.manage', 'domain' => 'Clinical'],
            ['name' => 'Break-Glass Emergency Access', 'slug' => 'clinical.break_glass', 'domain' => 'Clinical'],
            ['name' => 'Override Another Provider\'s Encounter', 'slug' => 'clinical.encounter.override', 'domain' => 'Clinical'],
            ['name' => 'Add Clinical Note', 'slug' => 'clinical.notes.create', 'domain' => 'Clinical'],
            ['name' => 'Record Patient Allergy', 'slug' => 'clinical.allergy.record', 'domain' => 'Clinical'],
            ['name' => 'Verify/Amend Patient Allergy', 'slug' => 'clinical.allergy.verify', 'domain' => 'Clinical'],

            // Patient Registry
            ['name' => 'Register Patient', 'slug' => 'patient.registry.create', 'domain' => 'Patient'],
            ['name' => 'View Patient Registry', 'slug' => 'patient.registry.view', 'domain' => 'Patient'],

            // Pharmacy
            ['name' => 'Create Prescription', 'slug' => 'pharmacy.prescription.create', 'domain' => 'Pharmacy'],
            ['name' => 'View Prescription', 'slug' => 'pharmacy.prescription.view', 'domain' => 'Pharmacy'],
            ['name' => 'Verify Prescription', 'slug' => 'pharmacy.prescription.verify', 'domain' => 'Pharmacy'],
            ['name' => 'Dispense Medication', 'slug' => 'pharmacy.dispense.execute', 'domain' => 'Pharmacy'],
            ['name' => 'Record Medication Reconciliation', 'slug' => 'pharmacy.medication-reconciliation.record', 'domain' => 'Pharmacy'],
            ['name' => 'Receive Pharmacy Stock Batch', 'slug' => 'pharmacy.inventory.receive', 'domain' => 'Pharmacy'],
            ['name' => 'Adjust Pharmacy Stock Batch', 'slug' => 'pharmacy.inventory.adjust', 'domain' => 'Pharmacy'],
            ['name' => 'View Pharmacy Inventory', 'slug' => 'pharmacy.inventory.view', 'domain' => 'Pharmacy'],

            // Inventory & Warehousing
            ['name' => 'View Inventory Locations', 'slug' => 'inventory.location.view', 'domain' => 'Inventory'],
            ['name' => 'Dispatch Stock Transfer', 'slug' => 'inventory.transfer.dispatch', 'domain' => 'Inventory'],
            ['name' => 'Confirm Stock Transfer', 'slug' => 'inventory.transfer.confirm', 'domain' => 'Inventory'],
            ['name' => 'Create Purchase Order', 'slug' => 'inventory.po.create', 'domain' => 'Inventory'],
            ['name' => 'Approve Purchase Order', 'slug' => 'inventory.po.approve', 'domain' => 'Inventory'],
            ['name' => 'Receive Goods Note (GRN)', 'slug' => 'inventory.grn.receive', 'domain' => 'Inventory'],
            ['name' => 'Initiate Stocktaking', 'slug' => 'inventory.stocktake.create', 'domain' => 'Inventory'],
            ['name' => 'Approve Stocktake Reconciliation', 'slug' => 'inventory.stocktake.approve', 'domain' => 'Inventory'],
            ['name' => 'Record DDA Narcotic Administration', 'slug' => 'inventory.dda.record', 'domain' => 'Inventory'],
            ['name' => 'View Stock Balances', 'slug' => 'inventory.stock.view', 'domain' => 'Inventory'],
            ['name' => 'View Item Catalog', 'slug' => 'inventory.catalog.view', 'domain' => 'Inventory'],
            ['name' => 'View Department Requisitions', 'slug' => 'inventory.requisition.view', 'domain' => 'Inventory'],
            ['name' => 'View Stock Transfers', 'slug' => 'inventory.transfer.view', 'domain' => 'Inventory'],
            ['name' => 'View Purchase Orders', 'slug' => 'inventory.po.view', 'domain' => 'Inventory'],
            ['name' => 'View Predictive Reorders', 'slug' => 'inventory.predictive.view', 'domain' => 'Inventory'],
            ['name' => 'View Goods Receipt Notes', 'slug' => 'inventory.grn.view', 'domain' => 'Inventory'],
            ['name' => 'View DDA Register', 'slug' => 'inventory.dda.view', 'domain' => 'Inventory'],
            ['name' => 'View Medical Gas Cylinders', 'slug' => 'inventory.gas.view', 'domain' => 'Inventory'],
            ['name' => 'View Stocktake Sessions', 'slug' => 'inventory.stocktake.view', 'domain' => 'Inventory'],
            ['name' => 'Manage Item Catalog', 'slug' => 'inventory.catalog.manage', 'domain' => 'Inventory'],
            ['name' => 'Submit Department Requisition', 'slug' => 'inventory.requisition.create', 'domain' => 'Inventory'],
            ['name' => 'Approve Department Requisition', 'slug' => 'inventory.requisition.approve', 'domain' => 'Inventory'],
            ['name' => 'Issue/Dispatch Department Requisition', 'slug' => 'inventory.requisition.issue', 'domain' => 'Inventory'],
            ['name' => 'Confirm Department Requisition Receipt', 'slug' => 'inventory.requisition.confirm', 'domain' => 'Inventory'],
            ['name' => 'Generate Predictive Reorder Purchase Orders', 'slug' => 'inventory.predictive.generate', 'domain' => 'Inventory'],

            // Billing & Financial
            ['name' => 'Create Invoice', 'slug' => 'billing.invoice.create', 'domain' => 'Billing'],
            ['name' => 'View Invoice', 'slug' => 'billing.invoice.view', 'domain' => 'Billing'],
            ['name' => 'Collect Payment', 'slug' => 'billing.payment.collect', 'domain' => 'Billing'],
            ['name' => 'Approve Discount', 'slug' => 'billing.discount.approve', 'domain' => 'Billing'],
            ['name' => 'Reconcile Till Shift', 'slug' => 'billing.shift.reconcile', 'domain' => 'Billing'],
            ['name' => 'Open Cashier Shift', 'slug' => 'billing.shift.open', 'domain' => 'Billing'],
            ['name' => 'Close Cashier Shift', 'slug' => 'billing.shift.close', 'domain' => 'Billing'],
            ['name' => 'Issue Refund', 'slug' => 'billing.refund.issue', 'domain' => 'Billing'],

            // Insurance
            ['name' => 'Create Insurance Claim', 'slug' => 'insurance.claim.create', 'domain' => 'Insurance'],
            ['name' => 'Submit Claims Batch', 'slug' => 'insurance.claim.submit', 'domain' => 'Insurance'],
            ['name' => 'Adjudicate Claim', 'slug' => 'insurance.claim.vet', 'domain' => 'Insurance'],
            ['name' => 'Adjudicate Remittance', 'slug' => 'insurance.claim.adjudicate', 'domain' => 'Insurance'],
            ['name' => 'Manage Insurance Tariffs', 'slug' => 'insurance.tariff.manage', 'domain' => 'Insurance'],
            ['name' => 'View Insurance Claims', 'slug' => 'insurance.claim.view', 'domain' => 'Insurance'],
            ['name' => 'Verify Policy Eligibility', 'slug' => 'insurance.policy.verify', 'domain' => 'Insurance'],
            ['name' => 'Request Pre-Authorization', 'slug' => 'insurance.preauth.create', 'domain' => 'Insurance'],

            // Laboratory
            ['name' => 'Order Lab Test', 'slug' => 'lab.order.create', 'domain' => 'Laboratory'],
            ['name' => 'Collect Specimen', 'slug' => 'lab.specimen.collect', 'domain' => 'Laboratory'],
            ['name' => 'Enter Lab Results', 'slug' => 'lab.result.record', 'domain' => 'Laboratory'],
            ['name' => 'Verify Lab Report', 'slug' => 'lab.result.verify', 'domain' => 'Laboratory'],
            ['name' => 'View Lab Orders & Results', 'slug' => 'lab.order.view', 'domain' => 'Laboratory'],
            ['name' => 'Manage Lab Test Catalog', 'slug' => 'lab.catalog.manage', 'domain' => 'Laboratory'],

            // Radiology
            ['name' => 'Order Diagnostic Imaging', 'slug' => 'radiology.order.create', 'domain' => 'Radiology'],
            ['name' => 'View Radiology Order', 'slug' => 'radiology.order.view', 'domain' => 'Radiology'],
            ['name' => 'Acquire Imaging Study', 'slug' => 'radiology.study.acquire', 'domain' => 'Radiology'],
            ['name' => 'Sign Radiology Report', 'slug' => 'radiology.report.sign', 'domain' => 'Radiology'],
            ['name' => 'Amend Radiology Report', 'slug' => 'radiology.report.amend', 'domain' => 'Radiology'],

            // Procedures & Surgery
            ['name' => 'Order Procedure', 'slug' => 'procedure.order.create', 'domain' => 'Procedure'],
            ['name' => 'Execute Minor Procedure', 'slug' => 'procedure.execute.dressing', 'domain' => 'Procedure'],
            ['name' => 'Book Operating Theatre', 'slug' => 'procedure.theatre.book', 'domain' => 'Procedure'],
            ['name' => 'Sign WHO Checklist', 'slug' => 'procedure.theatre.checklist', 'domain' => 'Procedure'],
            ['name' => 'Record PACU Recovery', 'slug' => 'procedure.theatre.pacu', 'domain' => 'Procedure'],
            ['name' => 'Execute Ordered Procedure', 'slug' => 'procedure.order.execute', 'domain' => 'Procedure'],
            ['name' => 'View Procedure Orders', 'slug' => 'procedure.order.view', 'domain' => 'Procedure'],

            // Inpatient
            ['name' => 'Admit Inpatient', 'slug' => 'inpatient.admission.create', 'domain' => 'Inpatient'],
            ['name' => 'Discharge Inpatient', 'slug' => 'inpatient.admission.discharge', 'domain' => 'Inpatient'],
            ['name' => 'Transfer Inpatient Admission', 'slug' => 'inpatient.admission.transfer', 'domain' => 'Inpatient'],
            ['name' => 'Transfer Inpatient Bed', 'slug' => 'inpatient.bed.transfer', 'domain' => 'Inpatient'],
            ['name' => 'Manage Bed Status', 'slug' => 'inpatient.bed.manage', 'domain' => 'Inpatient'],
            ['name' => 'Administer MAR Medication', 'slug' => 'inpatient.mar.administer', 'domain' => 'Inpatient'],
            ['name' => 'View Ward & Bed Status', 'slug' => 'inpatient.ward.view', 'domain' => 'Inpatient'],

            // Scheduling
            ['name' => 'Create Appointment', 'slug' => 'scheduling.appointment.create', 'domain' => 'Scheduling'],
            ['name' => 'Check In Appointment', 'slug' => 'scheduling.appointment.checkin', 'domain' => 'Scheduling'],
            ['name' => 'Call Queue Ticket', 'slug' => 'scheduling.queue.call', 'domain' => 'Scheduling'],
            ['name' => 'Transfer Queue Ticket', 'slug' => 'scheduling.queue.transfer', 'domain' => 'Scheduling'],
            ['name' => 'View Live Queue', 'slug' => 'scheduling.queue.view', 'domain' => 'Scheduling'],
            ['name' => 'View Appointments', 'slug' => 'scheduling.appointment.view', 'domain' => 'Scheduling'],

            // Reports & BI
            ['name' => 'View Clinical Analytics', 'slug' => 'reports.clinical.view', 'domain' => 'Reports'],
            ['name' => 'View Financial Intelligence', 'slug' => 'reports.financial.view', 'domain' => 'Reports'],
            ['name' => 'View Pharmacoeconomics', 'slug' => 'reports.pharmacoeconomic.view', 'domain' => 'Reports'],
            ['name' => 'View Analytics & Reports', 'slug' => 'reports.analytics.view', 'domain' => 'Reports'],

            // Identity & Audit
            ['name' => 'Manage Staff Accounts', 'slug' => 'identity.user.manage', 'domain' => 'Identity'],
            ['name' => 'Manage Roles & Permissions', 'slug' => 'identity.role.manage', 'domain' => 'Identity'],
            ['name' => 'Assign User Roles', 'slug' => 'identity.roles.assign', 'domain' => 'Identity'],
            ['name' => 'Manage Role Permissions', 'slug' => 'identity.permissions.manage', 'domain' => 'Identity'],
            ['name' => 'View Security Audit Trail', 'slug' => 'audit.log.view', 'domain' => 'Audit'],
            ['name' => 'Superadmin Platform Access', 'slug' => 'platform.superadmin.access', 'domain' => 'Platform'],
        ];

        $createdPermissions = [];
        foreach ($permissionsList as $p) {
            $perm = Permission::firstOrCreate(
                ['slug' => $p['slug']],
                ['name' => $p['name'], 'domain' => $p['domain']]
            );
            $createdPermissions[$p['slug']] = $perm->id;
        }

        // Standard System Roles
        $standardRoles = [
            'super-admin' => [
                'name' => 'Platform Superadmin',
                'desc' => 'Global SaaS platform control plane, tenant provisioning, and cross-hospital support.',
                'perms' => array_keys($createdPermissions),
            ],
            'tenant-admin' => [
                'name' => 'Tenant Administrator',
                'desc' => 'Executive administration, user provisioning, facility scoping, and audit oversight.',
                'perms' => array_filter(array_keys($createdPermissions), fn ($s) => $s !== 'platform.superadmin.access'),
            ],
            'doctor' => [
                'name' => 'Medical Officer / Clinician',
                'desc' => 'Clinical diagnosis, SOAP charting, lab ordering, Rx prescriptions, and theatre surgery.',
                'perms' => [
                    'clinical.encounter.create', 'clinical.encounter.view', 'clinical.encounter.update', 'clinical.encounter.close',
                    'clinical.encounter.override', 'clinical.break_glass',
                    'clinical.notes.sign', 'clinical.notes.create', 'clinical.vitals.record', 'clinical.diagnosis.manage',
                    'clinical.consent.record', 'clinical.referral.create', 'clinical.immunization.administer',
                    'clinical.anc.record', 'clinical.partograph.record', 'clinical.problem-list.manage',
                    'clinical.allergy.record', 'clinical.allergy.verify',
                    'pharmacy.prescription.create', 'pharmacy.prescription.view',
                    'lab.order.create', 'lab.order.view', 'procedure.order.create', 'procedure.order.execute', 'procedure.order.view',
                    'procedure.theatre.book', 'procedure.theatre.checklist', 'procedure.theatre.pacu',
                    'radiology.order.create', 'radiology.order.view',
                    'inpatient.admission.create', 'inpatient.admission.discharge', 'inpatient.admission.transfer', 'inpatient.bed.transfer',
                    'inpatient.ward.view',
                    'inventory.dda.record',
                    'patient.registry.view',
                    'reports.clinical.view',
                    'scheduling.queue.call', 'scheduling.queue.transfer', 'scheduling.queue.view', 'scheduling.appointment.view',
                ],
            ],
            'nurse' => [
                'name' => 'Nurse / Triage Officer',
                'desc' => 'Vital signs, queue triage, dressing desk procedures, MAR medication administration, and ward notes.',
                'perms' => [
                    'clinical.vitals.record', 'clinical.encounter.view', 'clinical.notes.create',
                    'clinical.immunization.administer', 'clinical.anc.record', 'clinical.partograph.record',
                    'clinical.allergy.record',
                    'procedure.execute.dressing', 'procedure.theatre.checklist', 'procedure.theatre.pacu', 'procedure.order.view',
                    'inpatient.ward.view', 'inpatient.bed.transfer', 'inpatient.bed.manage',
                    'inpatient.admission.transfer', 'inpatient.mar.administer', 'inventory.dda.record',
                    'inventory.dda.view', 'inventory.stock.view',
                    'inventory.requisition.create', 'inventory.requisition.confirm',
                    'scheduling.queue.call', 'scheduling.queue.transfer', 'scheduling.queue.view', 'scheduling.appointment.view',
                    'patient.registry.view',
                ],
            ],
            'lab-technologist' => [
                'name' => 'Laboratory Scientist / Technologist',
                'desc' => 'Specimen collection, bench testing, analyzer result entry, and laboratory verification.',
                'perms' => ['lab.specimen.collect', 'lab.result.record', 'lab.result.verify', 'lab.order.view', 'lab.catalog.manage', 'patient.registry.view'],
            ],
            'radiologist' => [
                'name' => 'Radiologist',
                'desc' => 'Diagnostic imaging report authoring, sign-off, and clinical amendment.',
                'perms' => ['radiology.order.view', 'radiology.study.acquire', 'radiology.report.sign', 'radiology.report.amend', 'patient.registry.view'],
            ],
            'pharmacist' => [
                'name' => 'Pharmacist',
                'desc' => 'Prescription safety review, drug-allergy vetting, clinical dispensing, and medication reconciliation.',
                'perms' => [
                    'pharmacy.prescription.view', 'pharmacy.prescription.verify', 'pharmacy.dispense.execute',
                    'pharmacy.medication-reconciliation.record', 'pharmacy.inventory.adjust', 'pharmacy.inventory.receive', 'pharmacy.inventory.view',
                    'inventory.location.view', 'inventory.stock.view', 'inventory.catalog.view', 'inventory.dda.view',
                    'inpatient.ward.view', 'clinical.allergy.verify',
                    'patient.registry.view',
                ],
            ],
            'cashier' => [
                'name' => 'Cashier / Billing Officer',
                'desc' => 'Patient invoice generation, cash/M-Pesa collection, and shift reconciliation.',
                'perms' => ['billing.invoice.create', 'billing.invoice.view', 'billing.payment.collect', 'billing.shift.reconcile', 'billing.shift.open', 'billing.shift.close', 'patient.registry.view'],
            ],
            'insurance-manager' => [
                'name' => 'Billing & Insurance Manager',
                'desc' => 'Insurance tariffs, NHIF claims batching, pre-auth approvals, discount authorization, and refunds.',
                'perms' => [
                    'insurance.claim.create', 'insurance.claim.submit', 'insurance.claim.vet', 'insurance.claim.adjudicate', 'insurance.claim.view',
                    'insurance.policy.verify', 'insurance.preauth.create',
                    'insurance.tariff.manage', 'billing.discount.approve', 'billing.refund.issue', 'reports.financial.view',
                    'billing.invoice.view',
                    'patient.registry.view',
                ],
            ],
            'inventory-officer' => [
                'name' => 'Inventory & Store Officer',
                'desc' => 'Purchase orders, GRN batch inward posting, stock transfers handshake, and physical stocktaking.',
                'perms' => [
                    'inventory.location.view', 'inventory.transfer.dispatch', 'inventory.transfer.confirm',
                    'inventory.po.create', 'inventory.po.approve', 'inventory.grn.receive',
                    'inventory.stocktake.create', 'inventory.stocktake.approve', 'reports.pharmacoeconomic.view',
                    'inventory.stock.view', 'inventory.catalog.view', 'inventory.requisition.view', 'inventory.transfer.view',
                    'inventory.po.view', 'inventory.predictive.view', 'inventory.grn.view', 'inventory.dda.view',
                    'inventory.gas.view', 'inventory.stocktake.view',
                    'inventory.catalog.manage', 'inventory.requisition.approve', 'inventory.requisition.issue',
                    'inventory.predictive.generate',
                ],
            ],
            'receptionist' => [
                'name' => 'Receptionist / Registration Clerk',
                'desc' => 'Universal patient registration, MPI search, appointment booking, and triage ticketing. Purely administrative: check-in creates the clinical encounter via a separate action (CheckInPatientAction) attributed to the appointment\'s assigned provider, not the receptionist — this role has no reason to hold any clinical.* permission.',
                'perms' => [
                    'patient.registry.create', 'patient.registry.view',
                    'scheduling.appointment.create', 'scheduling.appointment.checkin', 'scheduling.appointment.view',
                    'scheduling.queue.call', 'scheduling.queue.transfer', 'scheduling.queue.view',
                ],
            ],
            'auditor' => [
                'name' => 'Medical Auditor / Compliance',
                'desc' => 'Read-only inspection of clinical records, financial ledgers, and security audit trails.',
                'perms' => ['clinical.encounter.view', 'billing.invoice.view', 'insurance.claim.view', 'reports.clinical.view', 'reports.financial.view', 'reports.analytics.view', 'audit.log.view', 'patient.registry.view', 'inpatient.ward.view', 'pharmacy.prescription.view', 'inventory.dda.view'],
            ],
        ];

        foreach ($standardRoles as $slug => $rData) {
            $role = Role::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                ['name' => $rData['name'], 'description' => $rData['desc'], 'is_system' => true]
            );

            // Sync permissions
            $rolePermIds = [];
            foreach ($rData['perms'] as $pSlug) {
                if (isset($createdPermissions[$pSlug])) {
                    $rolePermIds[] = $createdPermissions[$pSlug];
                }
            }
            $role->permissions()->sync($rolePermIds);
        }

        // 'doctor' and 'cashier' already have a login-capable user created
        // above (section 4) — this covers the other 9 standard roles so
        // every role in the system has at least one account to actually
        // log in and test as, not just a permissioned-but-unreachable
        // Role row.
        $additionalRoleUsers = [
            'tenant-admin' => ['Neema', 'Kessy'],
            'nurse' => ['Fatuma', 'Juma'],
            'lab-technologist' => ['Baraka', 'Mwakalinga'],
            'radiologist' => ['Amina', 'Chuma'],
            'pharmacist' => ['Emmanuel', 'Shirima'],
            'insurance-manager' => ['Happiness', 'Ngowi'],
            'inventory-officer' => ['Daniel', 'Mrema'],
            'receptionist' => ['Zawadi', 'Komba'],
            'auditor' => ['Peter', 'Lyimo'],
        ];

        foreach ($additionalRoleUsers as $slug => [$firstName, $lastName]) {
            $role = Role::where('tenant_id', $tenant->id)->where('slug', $slug)->first();

            if (! $role) {
                continue;
            }

            $roleUser = User::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => "{$slug}@afyanova.local"],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'password_hash' => Hash::make('password123'),
                    'status' => 'active',
                ]
            );

            DB::table('role_assignments')->updateOrInsert(
                ['user_id' => $roleUser->id, 'role_id' => $role->id],
                ['id' => Uuid::uuid7()->toString(), 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // ==============================================================
        // 18. SECTION 18: PHYSICAL INVENTORY & WAREHOUSING SEEDING
        // ==============================================================
        $whCentral = InventoryLocation::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'WH-CENTRAL'],
            [
                'facility_id' => $facility->id,
                'name' => 'Central Medical Warehouse',
                'type' => 'Warehouse',
                'is_storage_only' => true,
                'is_dispensing_enabled' => false,
                'description' => 'Main bulk receiving warehouse and distribution center.',
            ]
        );

        $storeOpd = InventoryLocation::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'STORE-OPD-PHARM'],
            [
                'facility_id' => $facility->id,
                'name' => 'Outpatient Pharmacy Store',
                'type' => 'PharmacyStore',
                'is_dispensing_enabled' => true,
                'description' => 'Direct clinical dispensing pharmacy for OPD patients.',
            ]
        );

        $storeIpd = InventoryLocation::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'STORE-IPD-PHARM'],
            [
                'facility_id' => $facility->id,
                'name' => 'Inpatient Pharmacy Store',
                'type' => 'PharmacyStore',
                'is_dispensing_enabled' => true,
                'description' => 'Inpatient ward medication replenishment and unit dose dispensing.',
            ]
        );

        $cabTheatre = InventoryLocation::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'CAB-THEATRE-01'],
            [
                'facility_id' => $facility->id,
                'name' => 'Operating Theatre Surgical Cabinet',
                'type' => 'TheatreStore',
                'is_dispensing_enabled' => false,
                'description' => 'Emergency anesthesia and surgical consumables station.',
            ]
        );

        // Suppliers
        $supMsd = Supplier::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'SUP-MSD-01'],
            [
                'name' => 'Medical Stores Department (MSD Tanzania)',
                'tin_number' => '100-234-567',
                'contact_person' => 'Juma Mwinyi',
                'phone' => '+255222860150',
                'email' => 'orders@msd.go.tz',
                'payment_terms' => 'Net30',
            ]
        );

        $supMedico = Supplier::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'SUP-MED-02'],
            [
                'name' => 'Medico International Tanzania',
                'tin_number' => '101-889-432',
                'contact_person' => 'Farida Salim',
                'phone' => '+255754998811',
                'email' => 'sales@medico-tz.com',
                'payment_terms' => 'Net15',
            ]
        );

        // Purchase Order
        $po1 = PurchaseOrder::firstOrCreate(
            ['tenant_id' => $tenant->id, 'po_number' => 'PO-2026-0001'],
            [
                'supplier_id' => $supMsd->id,
                'facility_id' => $facility->id,
                'destination_location_id' => $whCentral->id,
                'order_date' => now()->subDays(5)->toDateString(),
                'expected_delivery_date' => now()->addDays(10)->toDateString(),
                'status' => 'Approved',
                'subtotal' => 2500000.00,
                'total_amount' => 2500000.00,
                'currency' => 'TZS',
                'ordered_by' => $primaryDoctor->id,
                'approved_by' => $primaryDoctor->id,
                'approved_at' => now()->subDays(4),
                'notes' => 'Emergency seasonal antimalarial and antibiotic bulk replenishment.',
            ]
        );

        // Populate stock balances across warehouses
        $activeBatches = InventoryBatch::where('tenant_id', $tenant->id)->get();
        foreach ($activeBatches as $idx => $b) {
            InventoryStockBalance::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'facility_id' => $facility->id,
                    'location_id' => $idx % 2 === 0 ? $whCentral->id : $storeOpd->id,
                    'medication_id' => $b->medication_id,
                    'batch_id' => $b->id,
                ],
                [
                    'quantity_on_hand' => $b->current_quantity,
                    'reorder_level' => 30,
                    'reorder_quantity' => 150,
                ]
            );
        }

        // Inter-store transfer handshake (In-Transit)
        if ($activeBatches->isNotEmpty()) {
            $transferBatch = $activeBatches->first();
            $trf = StockTransfer::firstOrCreate(
                ['tenant_id' => $tenant->id, 'transfer_number' => 'TRF-2026-0001'],
                [
                    'source_location_id' => $whCentral->id,
                    'destination_location_id' => $storeOpd->id,
                    'status' => 'Dispatched_In_Transit',
                    'dispatched_by' => $primaryDoctor->id,
                    'dispatched_at' => now()->subHours(4),
                    'notes' => 'Routine weekly stock replenishment from Central Warehouse.',
                ]
            );

            StockTransferItem::firstOrCreate(
                ['tenant_id' => $tenant->id, 'stock_transfer_id' => $trf->id, 'medication_id' => $transferBatch->medication_id],
                [
                    'batch_id' => $transferBatch->id,
                    'quantity_requested' => 100,
                    'quantity_dispatched' => 100,
                    'quantity_received' => 0,
                ]
            );
        }

        // ==============================================================
        // 19. SECTION 19: UNIVERSAL HOSPITAL MATERIALS & REQUISITIONS
        // ==============================================================
        $uomPc = UnitOfMeasure::firstOrCreate(
            ['tenant_id' => $tenant->id, 'symbol' => 'pc'],
            ['name' => 'Piece', 'description' => 'Single individual unit']
        );
        $uomBox = UnitOfMeasure::firstOrCreate(
            ['tenant_id' => $tenant->id, 'symbol' => 'bx'],
            ['name' => 'Box', 'description' => 'Pack of multiple pieces']
        );
        $uomDrum = UnitOfMeasure::firstOrCreate(
            ['tenant_id' => $tenant->id, 'symbol' => 'drm'],
            ['name' => 'Drum', 'description' => '20-Litre bulk container']
        );
        $uomLitre = UnitOfMeasure::firstOrCreate(
            ['tenant_id' => $tenant->id, 'symbol' => 'L'],
            ['name' => 'Litre', 'description' => 'Liquid metric volume']
        );
        $uomCyl = UnitOfMeasure::firstOrCreate(
            ['tenant_id' => $tenant->id, 'symbol' => 'cyl'],
            ['name' => 'Cylinder', 'description' => 'Pressurized medical gas cylinder']
        );
        $uomRoll = UnitOfMeasure::firstOrCreate(
            ['tenant_id' => $tenant->id, 'symbol' => 'roll'],
            ['name' => 'Roll', 'description' => 'Woven gauze or thermal roll']
        );

        // Universal Hospital Items across 9 Categories
        $universalCatalog = [
            // Surgical Consumables
            [
                'code' => 'MSD-SURG-001',
                'name' => 'IV Cannula G20 with Injection Port (Pink)',
                'generic' => 'Intravenous Cannula 20G',
                'category' => 'Surgical_Consumable',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 800.00,
                'price' => 1500.00,
                'billable' => true,
            ],
            [
                'code' => 'MSD-SURG-002',
                'name' => 'Surgical Suture Vicryl 2/0 Round Bodied',
                'generic' => 'Polyglactin 910 Absorbable Suture',
                'category' => 'Surgical_Consumable',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 36,
                'cost' => 4500.00,
                'price' => 7000.00,
                'billable' => true,
            ],
            [
                'code' => 'MSD-SURG-003',
                'name' => 'Surgical Gauze Roll 90cm x 100m',
                'generic' => 'Absorbent Cotton Gauze',
                'category' => 'Surgical_Consumable',
                'base_uom' => $uomRoll->id,
                'purch_uom' => $uomRoll->id,
                'ratio' => 1,
                'cost' => 35000.00,
                'price' => 45000.00,
                'billable' => true,
            ],
            // Lab Reagents
            [
                'code' => 'MSD-LAB-001',
                'name' => 'SD Bioline Malaria Ag P.f/Pan Rapid Test Kit',
                'generic' => 'Malaria Rapid Diagnostic Test (MRDT)',
                'category' => 'Lab_Reagent',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 25,
                'cost' => 1200.00,
                'price' => 3000.00,
                'billable' => true,
            ],
            [
                'code' => 'MSD-LAB-002',
                'name' => 'Vacutainer Blood Collection Tube K2 EDTA 4ml (Purple)',
                'generic' => 'EDTA Hematology Tube',
                'category' => 'Lab_Reagent',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 400.00,
                'price' => 800.00,
                'billable' => true,
            ],
            // IPC & Chemicals
            [
                'code' => 'MSD-IPC-001',
                'name' => 'Sodium Hypochlorite 3.5% (Jik Disinfectant) 20L',
                'generic' => 'Chlorine Surface Disinfectant',
                'category' => 'IPC_Chemical',
                'base_uom' => $uomLitre->id,
                'purch_uom' => $uomDrum->id,
                'ratio' => 20,
                'cost' => 2500.00,
                'price' => 0.00,
                'billable' => false,
            ],
            [
                'code' => 'MSD-IPC-002',
                'name' => 'Heavy Duty Clinical Yellow Waste Bags (Pack of 50)',
                'generic' => 'Biohazard Infectious Waste Liner',
                'category' => 'IPC_Chemical',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 50,
                'cost' => 500.00,
                'price' => 0.00,
                'billable' => false,
            ],
            // Stationery & MTUHA
            [
                'code' => 'MOH-STAT-005',
                'name' => 'Kitabu cha MTUHA Namba 5 (Daftari la OPD)',
                'generic' => 'National Health OPD Master Register',
                'category' => 'Stationery_MTUHA',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomPc->id,
                'ratio' => 1,
                'cost' => 15000.00,
                'price' => 0.00,
                'billable' => false,
            ],
            // Medical Gases
            [
                'code' => 'BOC-GAS-001',
                'name' => 'Medical Oxygen Gas Cylinder Size J (8500 Liters)',
                'generic' => 'Compressed Medical Oxygen USP',
                'category' => 'Medical_Gas',
                'base_uom' => $uomCyl->id,
                'purch_uom' => $uomCyl->id,
                'ratio' => 1,
                'cost' => 45000.00,
                'price' => 65000.00,
                'billable' => true,
            ],
            // DDA Controlled Narcotic
            [
                'code' => 'MSD-PHARM-DDA-01',
                'name' => 'Morphine Hydrochloride Injection 10mg/ml Ampoule',
                'generic' => 'Morphine Sulfate (DDA Schedule I)',
                'category' => 'Pharmaceutical',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 10,
                'cost' => 3500.00,
                'price' => 5500.00,
                'billable' => true,
            ],
            // Laboratory Consumables / Vacutainers
            [
                'code' => 'MSD-LAB-EDTA-01',
                'name' => 'Vacutainer EDTA K2/K3 Purple Top Tube 4ml',
                'generic' => 'Blood Collection Tube (EDTA K2/K3)',
                'category' => 'Laboratory Consumables',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 300.00,
                'price' => 500.00,
                'billable' => false,
            ],
            [
                'code' => 'MSD-LAB-SST-01',
                'name' => 'Vacutainer SST Gel Separator Gold Top Tube 5ml',
                'generic' => 'Blood Collection Tube (Serum Clot / Gel)',
                'category' => 'Laboratory Consumables',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 350.00,
                'price' => 600.00,
                'billable' => false,
            ],
            [
                'code' => 'MSD-LAB-RED-01',
                'name' => 'Vacutainer Plain Red Top Clot Activator Tube 5ml',
                'generic' => 'Blood Collection Tube (Plain Red)',
                'category' => 'Laboratory Consumables',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 280.00,
                'price' => 500.00,
                'billable' => false,
            ],
            [
                'code' => 'MSD-LAB-CIT-01',
                'name' => 'Vacutainer Sodium Citrate 3.2% Light Blue Top Tube 2.7ml',
                'generic' => 'Blood Collection Tube (Sodium Citrate 3.2%)',
                'category' => 'Laboratory Consumables',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 400.00,
                'price' => 650.00,
                'billable' => false,
            ],
            [
                'code' => 'MSD-LAB-GLU-01',
                'name' => 'Vacutainer Sodium Fluoride / Potassium Oxalate Grey Top Tube 2ml',
                'generic' => 'Blood Collection Tube (Fluoride Oxalate)',
                'category' => 'Laboratory Consumables',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 320.00,
                'price' => 550.00,
                'billable' => false,
            ],
            [
                'code' => 'MSD-LAB-URI-01',
                'name' => 'Sterile Urine Specimen Container 60ml Yellow Cap',
                'generic' => 'Specimen Container (Urine)',
                'category' => 'Laboratory Consumables',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 450.00,
                'price' => 800.00,
                'billable' => false,
            ],
            [
                'code' => 'MSD-LAB-STL-01',
                'name' => 'Stool Specimen Container with Scoop/Spoon 30ml',
                'generic' => 'Specimen Container (Stool)',
                'category' => 'Laboratory Consumables',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 450.00,
                'price' => 800.00,
                'billable' => false,
            ],
            [
                'code' => 'MSD-LAB-SPT-01',
                'name' => 'Sputum Specimen Container with Screw Cap 50ml',
                'generic' => 'Specimen Container (Sputum)',
                'category' => 'Laboratory Consumables',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 500.00,
                'price' => 900.00,
                'billable' => false,
            ],
            [
                'code' => 'MSD-LAB-SWB-01',
                'name' => 'Sterile Cotton / Dacron Swab with Transport Tube',
                'generic' => 'Microbiology Specimen Swab',
                'category' => 'Laboratory Consumables',
                'base_uom' => $uomPc->id,
                'purch_uom' => $uomBox->id,
                'ratio' => 100,
                'cost' => 350.00,
                'price' => 600.00,
                'billable' => false,
            ],
        ];

        $seededItems = [];
        foreach ($universalCatalog as $uItem) {
            $med = MedicationFormulary::firstOrCreate(
                ['tenant_id' => $tenant->id, 'generic_name' => $uItem['generic'], 'strength' => 'Standard'],
                [
                    'brand_name' => $uItem['name'],
                    'form' => $uItem['category'],
                    'route' => 'External',
                    'drug_class' => $uItem['category'],
                    'is_active' => true,
                ]
            );

            $item = ItemMaster::firstOrCreate(
                ['tenant_id' => $tenant->id, 'item_code' => $uItem['code']],
                [
                    'name' => $uItem['name'],
                    'generic_name' => $uItem['generic'],
                    'category' => $uItem['category'],
                    'base_uom_id' => $uItem['base_uom'],
                    'purchasing_uom_id' => $uItem['purch_uom'],
                    'conversion_ratio' => $uItem['ratio'],
                    'unit_cost_price' => $uItem['cost'],
                    'unit_selling_price' => $uItem['price'],
                    'is_billable' => $uItem['billable'],
                    'is_dda_narcotic' => str_contains($uItem['code'], 'DDA'),
                    'medication_id' => $med->id,
                ]
            );

            // Create initial batch and stock balance in Central Warehouse for all universal items
            $batch = InventoryBatch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'batch_number' => 'BATCH-'.$uItem['code']],
                [
                    'facility_id' => $facility->id,
                    'medication_id' => $med->id,
                    'initial_quantity' => 500,
                    'current_quantity' => 500,
                    'unit_cost' => $uItem['cost'],
                    'unit_selling_price' => $uItem['price'],
                    'expiry_date' => now()->addMonths(24)->toDateString(),
                    'status' => 'Active',
                ]
            );

            InventoryStockBalance::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'facility_id' => $facility->id,
                    'location_id' => $whCentral->id,
                    'medication_id' => $med->id,
                    'batch_id' => $batch->id,
                ],
                [
                    'quantity_on_hand' => 500,
                    'reorder_level' => 50,
                    'reorder_quantity' => 200,
                ]
            );

            $seededItems[$uItem['code']] = $item;
        }

        // Also ensure all clinical pharmaceutical formularies are registered in ItemMaster
        $allMeds = MedicationFormulary::where('tenant_id', $tenant->id)->get();
        foreach ($allMeds as $m) {
            $itemCode = 'MSD-PHARM-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $m->generic_name), 0, 8));
            ItemMaster::firstOrCreate(
                ['tenant_id' => $tenant->id, 'item_code' => $itemCode],
                [
                    'name' => "{$m->generic_name} ({$m->brand_name} {$m->strength})",
                    'generic_name' => $m->generic_name,
                    'category' => 'Pharmaceutical',
                    'base_uom_id' => $uomPc->id,
                    'purchasing_uom_id' => $uomBox->id,
                    'conversion_ratio' => 10,
                    'unit_cost_price' => 1000.00,
                    'unit_selling_price' => 2000.00,
                    'is_billable' => true,
                    'medication_id' => $m->id,
                ]
            );
        }

        // Seed Department Store Requisition (Store Indent)
        $maternityDept = Department::firstOrCreate(
            ['facility_id' => $facility->id, 'code' => 'DEPT-MAT'],
            ['name' => 'Maternity & Labour Ward']
        );

        $storeMaternity = InventoryLocation::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'CAB-MATERNITY-01'],
            [
                'facility_id' => $facility->id,
                'name' => 'Maternity Labour Ward Cabinet',
                'type' => 'WardCabinet',
                'is_dispensing_enabled' => false,
                'description' => 'Sub-store for delivery kits, oxytocin and IV cannulas.',
            ]
        );

        $req1 = DepartmentRequisition::firstOrCreate(
            ['tenant_id' => $tenant->id, 'requisition_number' => 'REQ-2026-0001'],
            [
                'facility_id' => $facility->id,
                'department_id' => $maternityDept->id,
                'source_location_id' => $whCentral->id,
                'destination_location_id' => $storeMaternity->id,
                'requisition_type' => 'Routine_Weekly',
                'status' => 'Submitted',
                'requested_by' => $primaryDoctor->id,
                'submitted_at' => now()->subHours(6),
                'notes' => 'Weekly replenishment of delivery consumables, IV cannulas and sutures.',
            ]
        );

        if (isset($seededItems['MSD-SURG-001'])) {
            DepartmentRequisitionItem::firstOrCreate(
                ['tenant_id' => $tenant->id, 'department_requisition_id' => $req1->id, 'item_id' => $seededItems['MSD-SURG-001']->id],
                [
                    'quantity_requested' => 50,
                    'quantity_approved' => 50,
                    'quantity_dispatched' => 0,
                    'quantity_received' => 0,
                ]
            );
        }

        // Seed Medical Oxygen Cylinders
        $cylinders = [
            ['serial' => 'OXY-TZ-8801', 'type' => 'Oxygen', 'size' => 'Size_J', 'liters' => 8500, 'status' => 'Full_In_Store'],
            ['serial' => 'OXY-TZ-8802', 'type' => 'Oxygen', 'size' => 'Size_J', 'liters' => 8500, 'status' => 'In_Use_Ward', 'bed' => 'ICU Bed 02'],
            ['serial' => 'OXY-TZ-8803', 'type' => 'Oxygen', 'size' => 'Size_G', 'liters' => 3400, 'status' => 'Empty_Return_Bay'],
        ];

        foreach ($cylinders as $cyl) {
            MedicalGasCylinder::firstOrCreate(
                ['tenant_id' => $tenant->id, 'cylinder_serial_number' => $cyl['serial']],
                [
                    'facility_id' => $facility->id,
                    'gas_type' => $cyl['type'],
                    'cylinder_size' => $cyl['size'],
                    'volume_liters' => $cyl['liters'],
                    'current_location_id' => $whCentral->id,
                    'status' => $cyl['status'],
                    'assigned_ward_bed' => $cyl['bed'] ?? null,
                    'last_refilled_at' => now()->subDays(12),
                ]
            );
        }

        // AuthorizationService caches each user's resolved permission map for
        // an hour (see clearUserCache()); the two real runtime mutation paths
        // (AssignUserRoleAction, UpdateRolePermissionsAction) invalidate it
        // correctly, but this seeder writes role/permission rows directly via
        // Eloquent sync() and has no per-user list to target, so a role
        // change here silently keeps serving stale grants to a warm server
        // until the TTL expires. Flush on every run so re-seeding against a
        // running dev server takes effect immediately.
        Cache::flush();

        echo "Enterprise Seeding Complete!\n";
    }
}
