<?php

namespace Database\Seeders;

use App\Core\Context\TenantContext;
use App\Domains\Billing\Models\CashierShift;
use App\Domains\Billing\Models\ChargeMasterItem;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Clinical\Models\Allergy;
use App\Domains\Clinical\Models\ClinicalNote;
use App\Domains\Clinical\Models\ClinicalVital;
use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Insurance\Models\InsuranceClaimItem;
use App\Domains\Insurance\Models\InsuranceProvider;
use App\Domains\Insurance\Models\InsuranceScheme;
use App\Domains\Insurance\Models\PatientPolicy;
use App\Domains\Insurance\Models\PreAuthorization;
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
use App\Domains\Patient\Models\PatientContact;
use App\Domains\Patient\Models\PatientIdentifier;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Pharmacy\Models\StockMovement;
use App\Domains\Procedure\Models\OperatingSuite;
use App\Domains\Procedure\Models\PacuRecoveryRecord;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Procedure\Models\ProcedureOrder;
use App\Domains\Procedure\Models\SurgicalBooking;
use App\Domains\Procedure\Models\WhoSurgicalChecklist;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
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

        // 5. Create Multiple Realistic Patients
        $patientsData = [
            [
                'first_name' => 'Asha',
                'middle_name' => 'Ali',
                'last_name' => 'Juma',
                'dob' => '1990-05-15',
                'gender' => 'Female',
                'blood_group' => 'A+',
                'primary_mrn' => 'MRN-2024-001',
                'phone' => '+255712345678',
                'nida' => '19900515-11111-00001-22',
                'allergy' => 'Penicillin',
                'allergy_sev' => 'Severe',
            ],
            [
                'first_name' => 'Baraka',
                'middle_name' => 'Simon',
                'last_name' => 'Kimaro',
                'dob' => '1982-11-20',
                'gender' => 'Male',
                'blood_group' => 'O+',
                'primary_mrn' => 'MRN-2024-002',
                'phone' => '+255754112233',
                'nida' => '19821120-22222-00002-33',
                'allergy' => 'Aspirin',
                'allergy_sev' => 'Moderate',
            ],
            [
                'first_name' => 'Neema',
                'middle_name' => 'Joseph',
                'last_name' => 'Mbowe',
                'dob' => '1995-08-20',
                'gender' => 'Female',
                'blood_group' => 'B+',
                'primary_mrn' => 'MRN-2024-003',
                'phone' => '+255788990011',
                'nida' => '19950820-33333-00003-44',
                'allergy' => null,
                'allergy_sev' => null,
            ],
            [
                'first_name' => 'Emmanuel',
                'middle_name' => 'Lucas',
                'last_name' => 'Mushi',
                'dob' => '1968-03-10',
                'gender' => 'Male',
                'blood_group' => 'AB+',
                'primary_mrn' => 'MRN-2024-004',
                'phone' => '+255762445566',
                'nida' => '19680310-44444-00004-55',
                'allergy' => 'Sulfa drugs',
                'allergy_sev' => 'High',
            ],
            [
                'first_name' => 'Halima',
                'middle_name' => 'Said',
                'last_name' => 'Bakari',
                'dob' => '1964-07-25',
                'gender' => 'Female',
                'blood_group' => 'O-',
                'primary_mrn' => 'MRN-2024-005',
                'phone' => '+255713998877',
                'nida' => '19640725-55555-00005-66',
                'allergy' => null,
                'allergy_sev' => null,
            ],
            [
                'first_name' => 'Kassim',
                'middle_name' => 'Rashid',
                'last_name' => 'Majaliwa',
                'dob' => '1985-09-12',
                'gender' => 'Male',
                'blood_group' => 'A-',
                'primary_mrn' => 'MRN-2024-006',
                'phone' => '+255789123456',
                'nida' => '19850912-66666-00006-77',
                'allergy' => null,
                'allergy_sev' => null,
            ],
        ];

        $createdPatients = [];
        foreach ($patientsData as $pData) {
            $patient = Patient::firstOrCreate(
                ['tenant_id' => $tenant->id, 'primary_mrn' => $pData['primary_mrn']],
                [
                    'first_name' => $pData['first_name'],
                    'middle_name' => $pData['middle_name'],
                    'last_name' => $pData['last_name'],
                    'dob' => $pData['dob'],
                    'gender' => $pData['gender'],
                    'blood_group' => $pData['blood_group'],
                    'status' => 'Active',
                ]
            );

            // Add Identifier
            if (! empty($pData['nida'])) {
                PatientIdentifier::firstOrCreate(
                    ['patient_id' => $patient->id, 'type' => 'NIDA'],
                    ['identifier_value' => $pData['nida'], 'facility_id' => $facility->id, 'is_primary' => true]
                );
            }

            // Add Contact
            if (! empty($pData['phone'])) {
                PatientContact::firstOrCreate(
                    ['patient_id' => $patient->id, 'contact_type' => 'Mobile Phone'],
                    ['value' => $pData['phone'], 'is_verified' => true]
                );
            }

            // Add Allergy
            if (! empty($pData['allergy'])) {
                Allergy::firstOrCreate(
                    ['patient_id' => $patient->id, 'allergen' => $pData['allergy']],
                    ['allergen_type' => 'Drug', 'severity' => $pData['allergy_sev'], 'status' => 'Active', 'recorded_by' => $doctor->id]
                );
            }

            $createdPatients[] = $patient;
        }

        // 6. Create Medication Formularies
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

        // 7. Create Encounters & Clinical Notes
        $firstPatient = $createdPatients[0];
        $secondPatient = $createdPatients[1];
        $thirdPatient = $createdPatients[2];

        $enc1 = Encounter::firstOrCreate(
            ['tenant_id' => $tenant->id, 'patient_id' => $firstPatient->id, 'encounter_type' => 'OPD'],
            [
                'facility_id' => $facility->id,
                'department_id' => null,
                'provider_id' => $doctor->id,
                'status' => 'In Progress',
                'reason_for_visit' => 'Acute fever, cough and general body weakness for 3 days',
                'start_time' => now()->subMinutes(25),
            ]
        );

        ClinicalVital::firstOrCreate(
            ['encounter_id' => $enc1->id],
            [
                'patient_id' => $firstPatient->id,
                'temperature_c' => 38.6,
                'heart_rate' => 88,
                'systolic_bp' => 125,
                'diastolic_bp' => 82,
                'respiratory_rate' => 18,
                'oxygen_saturation' => 97,
                'weight_kg' => 64.0,
                'height_cm' => 165.0,
                'bmi' => 23.51,
                'recorded_by' => $doctor->id,
                'recorded_at' => now()->subMinutes(20),
            ]
        );

        ClinicalNote::firstOrCreate(
            ['encounter_id' => $enc1->id],
            [
                'patient_id' => $firstPatient->id,
                'author_id' => $doctor->id,
                'note_type' => 'SOAP',
                'content' => [
                    'subjective' => 'Patient presents with 3-day history of high fever, headache, joint pains, and chills.',
                    'objective' => 'Febrile (38.6°C), clear lung fields, no cervical lymphadenopathy.',
                    'assessment' => 'Suspected Acute Malaria / Upper Respiratory Tract Infection.',
                    'plan' => 'Perform mRDT malaria test, prescribe Artemether-Lumefantrine and Paracetamol.',
                ],
                'is_signed' => false,
            ]
        );

        $enc2 = Encounter::firstOrCreate(
            ['tenant_id' => $tenant->id, 'patient_id' => $secondPatient->id, 'encounter_type' => 'OPD'],
            [
                'facility_id' => $facility->id,
                'department_id' => null,
                'provider_id' => $doctor->id,
                'status' => 'In Progress',
                'reason_for_visit' => 'Routine Hypertension and Blood Sugar follow-up',
                'start_time' => now()->subMinutes(10),
            ]
        );

        ClinicalVital::firstOrCreate(
            ['encounter_id' => $enc2->id],
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $secondPatient->id,
                'temperature_c' => 37.1,
                'heart_rate' => 74,
                'systolic_bp' => 135,
                'diastolic_bp' => 88,
                'respiratory_rate' => 16,
                'oxygen_saturation' => 99,
                'weight_kg' => 78.0,
                'height_cm' => 174.0,
                'bmi' => 25.76,
                'recorded_by' => $doctor->id,
                'recorded_at' => now()->subMinutes(10),
            ]
        );

        // 3. Triage Encounter with Pre-recorded Vitals (Ready for Doctor)
        $enc3 = Encounter::firstOrCreate(
            ['tenant_id' => $tenant->id, 'patient_id' => $thirdPatient->id, 'encounter_type' => 'OPD'],
            [
                'facility_id' => $facility->id,
                'department_id' => null,
                'provider_id' => null,
                'status' => 'Triage',
                'reason_for_visit' => 'Persistent frontal headache and dizziness since morning',
                'start_time' => now()->subMinutes(15),
            ]
        );

        ClinicalVital::firstOrCreate(
            ['encounter_id' => $enc3->id],
            [
                'tenant_id' => $tenant->id,
                'patient_id' => $thirdPatient->id,
                'temperature_c' => 37.2,
                'heart_rate' => 74,
                'systolic_bp' => 118,
                'diastolic_bp' => 76,
                'respiratory_rate' => 16,
                'oxygen_saturation' => 99,
                'weight_kg' => 58.0,
                'height_cm' => 162.0,
                'bmi' => 22.10,
                'recorded_by' => $doctor->id,
                'recorded_at' => now()->subMinutes(12),
            ]
        );

        // 8. Create Live Queue Tickets
        QueueTicket::firstOrCreate(
            ['tenant_id' => $tenant->id, 'ticket_number' => 'A-101'],
            [
                'facility_id' => $facility->id,
                'patient_id' => $firstPatient->id,
                'encounter_id' => $enc1->id,
                'current_service_point' => 'Doctor',
                'priority' => 'Urgent',
                'status' => 'Waiting',
                'joined_queue_at' => now()->subMinutes(30),
            ]
        );

        QueueTicket::firstOrCreate(
            ['tenant_id' => $tenant->id, 'ticket_number' => 'A-102'],
            [
                'facility_id' => $facility->id,
                'patient_id' => $thirdPatient->id,
                'encounter_id' => $enc3->id,
                'current_service_point' => 'Doctor',
                'priority' => 'Normal',
                'status' => 'Waiting',
                'joined_queue_at' => now()->subMinutes(15),
            ]
        );

        // 9. Create Appointment
        Appointment::firstOrCreate(
            ['tenant_id' => $tenant->id, 'patient_id' => $firstPatient->id],
            [
                'facility_id' => $facility->id,
                'provider_id' => $doctor->id,
                'scheduled_time' => now()->addHours(2),
                'duration_minutes' => 30,
                'appointment_type' => 'Clinical Review',
                'status' => 'Scheduled',
            ]
        );

        // 10. Create Invoices and Detailed Charges Items for Billing Desk
        $inv1 = Invoice::firstOrCreate(
            ['tenant_id' => $tenant->id, 'invoice_number' => 'INV-2026-0001'],
            [
                'facility_id' => $facility->id,
                'encounter_id' => $enc1->id,
                'patient_id' => $firstPatient->id,
                'total_amount' => 45000,
                'paid_amount' => 45000,
                'status' => 'Paid',
                'issued_at' => now()->subHours(4),
            ]
        );

        InvoiceLineItem::firstOrCreate(
            ['invoice_id' => $inv1->id, 'description' => 'General OPD Medical Consultation'],
            [
                'tenant_id' => $tenant->id,
                'category' => 'Consultation',
                'quantity' => 1,
                'unit_price' => 20000,
                'total_price' => 20000,
            ]
        );

        InvoiceLineItem::firstOrCreate(
            ['invoice_id' => $inv1->id, 'description' => 'Rapid Malaria mRDT & Blood Film'],
            [
                'tenant_id' => $tenant->id,
                'category' => 'Lab',
                'quantity' => 1,
                'unit_price' => 15000,
                'total_price' => 15000,
            ]
        );

        InvoiceLineItem::firstOrCreate(
            ['invoice_id' => $inv1->id, 'description' => 'Artemether / Lumefantrine 20/120mg (6-dose pack)'],
            [
                'tenant_id' => $tenant->id,
                'category' => 'Pharmacy',
                'quantity' => 1,
                'unit_price' => 10000,
                'total_price' => 10000,
            ]
        );

        $inv2 = Invoice::firstOrCreate(
            ['tenant_id' => $tenant->id, 'invoice_number' => 'INV-2026-0002'],
            [
                'facility_id' => $facility->id,
                'encounter_id' => $enc2->id,
                'patient_id' => $secondPatient->id,
                'total_amount' => 65000,
                'paid_amount' => 20000,
                'status' => 'Partial',
                'issued_at' => now()->subHours(2),
            ]
        );

        InvoiceLineItem::firstOrCreate(
            ['invoice_id' => $inv2->id, 'description' => 'Specialist Physician Consultation'],
            [
                'tenant_id' => $tenant->id,
                'category' => 'Consultation',
                'quantity' => 1,
                'unit_price' => 30000,
                'total_price' => 30000,
            ]
        );

        InvoiceLineItem::firstOrCreate(
            ['invoice_id' => $inv2->id, 'description' => 'Full Blood Picture & Lipid Profile'],
            [
                'tenant_id' => $tenant->id,
                'category' => 'Lab',
                'quantity' => 1,
                'unit_price' => 25000,
                'total_price' => 25000,
            ]
        );

        InvoiceLineItem::firstOrCreate(
            ['invoice_id' => $inv2->id, 'description' => 'Amlodipine 5mg (30 Tablets)'],
            [
                'tenant_id' => $tenant->id,
                'category' => 'Pharmacy',
                'quantity' => 1,
                'unit_price' => 10000,
                'total_price' => 10000,
            ]
        );

        $inv3 = Invoice::firstOrCreate(
            ['tenant_id' => $tenant->id, 'invoice_number' => 'INV-2026-0003'],
            [
                'facility_id' => $facility->id,
                'encounter_id' => null,
                'patient_id' => $thirdPatient->id,
                'total_amount' => 55000,
                'paid_amount' => 0,
                'status' => 'Issued',
                'issued_at' => now()->subMinutes(45),
            ]
        );

        InvoiceLineItem::firstOrCreate(
            ['invoice_id' => $inv3->id, 'description' => 'Emergency Triage Assessment & Vitals'],
            [
                'tenant_id' => $tenant->id,
                'category' => 'Consultation',
                'quantity' => 1,
                'unit_price' => 15000,
                'total_price' => 15000,
            ]
        );

        InvoiceLineItem::firstOrCreate(
            ['invoice_id' => $inv3->id, 'description' => 'Serum Electrolytes & Renal Function Panel'],
            [
                'tenant_id' => $tenant->id,
                'category' => 'Lab',
                'quantity' => 1,
                'unit_price' => 25000,
                'total_price' => 25000,
            ]
        );

        InvoiceLineItem::firstOrCreate(
            ['invoice_id' => $inv3->id, 'description' => 'IV Normal Saline 500ml & Infusion Set'],
            [
                'tenant_id' => $tenant->id,
                'category' => 'Procedure',
                'quantity' => 1,
                'unit_price' => 15000,
                'total_price' => 15000,
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

        // Seed Sample Completed Lab Order for Encounter 1
        $fbpTest = LabTest::where('test_code', 'LAB-FBP-001')->first();
        $mrdtTest = LabTest::where('test_code', 'LAB-MAL-001')->first();

        if ($enc1 && $fbpTest && $mrdtTest) {
            $sampleOrder = LabOrder::firstOrCreate(
                ['tenant_id' => $tenant->id, 'order_number' => "LAB-{$today}-001"],
                [
                    'encounter_id' => $enc1->id,
                    'patient_id' => $enc1->patient_id,
                    'ordering_provider_id' => $doctor->id,
                    'priority' => 'STAT',
                    'clinical_notes' => 'Acute fever x 3 days, chills, suspect malaria and anemia',
                    'status' => 'Completed',
                    'ordered_at' => now()->subHours(2),
                    'collected_at' => now()->subMinutes(90),
                    'completed_at' => now()->subMinutes(30),
                ]
            );

            LabOrderItem::firstOrCreate(
                ['lab_order_id' => $sampleOrder->id, 'lab_test_id' => $mrdtTest->id],
                [
                    'tenant_id' => $tenant->id,
                    'price' => $mrdtTest->price,
                    'status' => 'Completed',
                    'specimen_barcode' => 'BC-MAL-8831',
                    'results' => [
                        'Malaria Antigen (P. falciparum/Pan)' => 'Positive (High Density)',
                    ],
                    'technician_remarks' => 'Confirmed trophozoites observed on thin blood smear.',
                    'has_critical_value' => true,
                    'critical_value_alerted_at' => now()->subMinutes(30),
                    'performed_by_id' => $doctor->id,
                ]
            );

            LabOrderItem::firstOrCreate(
                ['lab_order_id' => $sampleOrder->id, 'lab_test_id' => $fbpTest->id],
                [
                    'tenant_id' => $tenant->id,
                    'price' => $fbpTest->price,
                    'status' => 'Completed',
                    'specimen_barcode' => 'BC-FBP-8832',
                    'results' => [
                        'Hemoglobin (Hb)' => '6.4',
                        'White Blood Cells (WBC)' => '14.2',
                        'Platelets' => '110',
                        'Hematocrit (HCT)' => '21.5',
                    ],
                    'technician_remarks' => 'Severe microcytic hypochromic anemia with moderate leukocytosis.',
                    'has_critical_value' => true,
                    'critical_value_alerted_at' => now()->subMinutes(30),
                    'performed_by_id' => $doctor->id,
                    'verified_by_id' => $doctor->id,
                ]
            );
        }

        // Seed Sample Pending Phlebotomy Order for Encounter 2 (Baraka Kimaro)
        $rftTest = LabTest::where('test_code', 'LAB-RFT-001')->first();
        $uriTest = LabTest::where('test_code', 'LAB-URI-001')->first();

        if ($enc2 && $rftTest && $uriTest) {
            $pendingOrder = LabOrder::firstOrCreate(
                ['tenant_id' => $tenant->id, 'order_number' => "LAB-{$today}-002"],
                [
                    'encounter_id' => $enc2->id,
                    'patient_id' => $enc2->patient_id,
                    'ordering_provider_id' => $doctor->id,
                    'priority' => 'Urgent',
                    'clinical_notes' => 'Chronic hypertension monitoring, check renal profile and urinalysis',
                    'status' => 'Ordered',
                    'ordered_at' => now()->subMinutes(45),
                ]
            );

            LabOrderItem::firstOrCreate(
                ['lab_order_id' => $pendingOrder->id, 'lab_test_id' => $rftTest->id],
                [
                    'tenant_id' => $tenant->id,
                    'price' => $rftTest->price,
                    'status' => 'Pending',
                ]
            );

            LabOrderItem::firstOrCreate(
                ['lab_order_id' => $pendingOrder->id, 'lab_test_id' => $uriTest->id],
                [
                    'tenant_id' => $tenant->id,
                    'price' => $uriTest->price,
                    'status' => 'Pending',
                ]
            );
        }

        // Seed Sample In-Testing Order for Encounter 3 (Neema Mbowe)
        $gluTest = LabTest::where('test_code', 'LAB-GLU-001')->first();
        $lipTest = LabTest::where('test_code', 'LAB-LIP-001')->first();

        if ($enc3 && $gluTest && $lipTest) {
            $testingOrder = LabOrder::firstOrCreate(
                ['tenant_id' => $tenant->id, 'order_number' => "LAB-{$today}-003"],
                [
                    'encounter_id' => $enc3->id,
                    'patient_id' => $enc3->patient_id,
                    'ordering_provider_id' => $doctor->id,
                    'priority' => 'Routine',
                    'clinical_notes' => 'Routine metabolic screening and lipid profiling',
                    'status' => 'Sample Collected',
                    'ordered_at' => now()->subHours(1),
                    'collected_at' => now()->subMinutes(25),
                ]
            );

            LabOrderItem::firstOrCreate(
                ['lab_order_id' => $testingOrder->id, 'lab_test_id' => $gluTest->id],
                [
                    'tenant_id' => $tenant->id,
                    'price' => $gluTest->price,
                    'status' => 'Sample Collected',
                    'specimen_barcode' => 'ACC-2026-GLU771',
                    'technician_remarks' => 'Specimen loaded on automated chemistry analyzer',
                ]
            );

            LabOrderItem::firstOrCreate(
                ['lab_order_id' => $testingOrder->id, 'lab_test_id' => $lipTest->id],
                [
                    'tenant_id' => $tenant->id,
                    'price' => $lipTest->price,
                    'status' => 'Sample Collected',
                    'specimen_barcode' => 'ACC-2026-LIP772',
                    'technician_remarks' => 'Serum centrifuged, awaiting analyzer batch run',
                ]
            );
        }

        // 11. Create Realistic Prescriptions for Active Encounters
        $coartemMed = MedicationFormulary::where('brand_name', 'Coartem')->first();
        $panadolMed = MedicationFormulary::where('brand_name', 'Panadol')->first();
        $norvascMed = MedicationFormulary::where('brand_name', 'Norvasc')->first();

        if ($enc1 && $coartemMed) {
            Prescription::firstOrCreate(
                ['encounter_id' => $enc1->id, 'medication_id' => $coartemMed->id],
                [
                    'tenant_id' => $tenant->id,
                    'patient_id' => $enc1->patient_id,
                    'prescriber_id' => $doctor->id,
                    'dosage' => '4 tablets',
                    'route' => 'Oral',
                    'frequency' => 'BID (Twice Daily)',
                    'duration_days' => 3,
                    'quantity' => 24,
                    'instructions' => 'Take with fatty meal or milk for optimal absorption',
                    'status' => 'Prescribed',
                ]
            );
        }

        if ($enc1 && $panadolMed) {
            Prescription::firstOrCreate(
                ['encounter_id' => $enc1->id, 'medication_id' => $panadolMed->id],
                [
                    'tenant_id' => $tenant->id,
                    'patient_id' => $enc1->patient_id,
                    'prescriber_id' => $doctor->id,
                    'dosage' => '1000mg (2 tabs)',
                    'route' => 'Oral',
                    'frequency' => 'TID (Three Times Daily)',
                    'duration_days' => 5,
                    'quantity' => 30,
                    'instructions' => 'For fever and body pains. Do not exceed 4000mg/24h',
                    'status' => 'Prescribed',
                ]
            );
        }

        if ($enc2 && $norvascMed) {
            Prescription::firstOrCreate(
                ['encounter_id' => $enc2->id, 'medication_id' => $norvascMed->id],
                [
                    'tenant_id' => $tenant->id,
                    'patient_id' => $enc2->patient_id,
                    'prescriber_id' => $doctor->id,
                    'dosage' => '5mg (1 tab)',
                    'route' => 'Oral',
                    'frequency' => 'OD (Once Daily)',
                    'duration_days' => 30,
                    'quantity' => 30,
                    'instructions' => 'Take in the morning for blood pressure control',
                    'status' => 'Prescribed',
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

        // Seed Sample Active Admissions
        $mmwBed1 = $allCreatedBeds['MMW-BED-01'] ?? null;
        if ($mmwBed1 && $firstPatient) {
            $mmwBed1->update(['status' => 'Occupied']);
            Admission::firstOrCreate(
                ['tenant_id' => $tenant->id, 'admission_number' => 'ADM-2026-0001'],
                [
                    'facility_id' => $facility->id,
                    'encounter_id' => $enc1->id ?? null,
                    'patient_id' => $firstPatient->id,
                    'admitting_doctor_id' => $doctor->id,
                    'ward_id' => $mmwBed1->ward_id,
                    'bed_id' => $mmwBed1->id,
                    'admission_reason' => 'Acute severe malaria with chills, dehydration, and low hemoglobin requiring IV Artesunate and supportive monitoring.',
                    'provisional_diagnosis' => 'Severe Falciparum Malaria with Anemia',
                    'admitted_at' => now()->subDays(2),
                    'status' => 'Admitted',
                ]
            );
        }

        $fswBed1 = $allCreatedBeds['FSW-BED-01'] ?? null;
        if ($fswBed1 && $secondPatient) {
            $fswBed1->update(['status' => 'Occupied']);
            Admission::firstOrCreate(
                ['tenant_id' => $tenant->id, 'admission_number' => 'ADM-2026-0002'],
                [
                    'facility_id' => $facility->id,
                    'encounter_id' => $enc2->id ?? null,
                    'patient_id' => $secondPatient->id,
                    'admitting_doctor_id' => $doctor->id,
                    'ward_id' => $fswBed1->ward_id,
                    'bed_id' => $fswBed1->id,
                    'admission_reason' => 'Hypertensive crisis with persistent severe headache requiring intravenous infusion and continuous BP telemetry.',
                    'provisional_diagnosis' => 'Hypertensive Urgency / Severe Essential Hypertension',
                    'admitted_at' => now()->subDays(1),
                    'status' => 'Admitted',
                ]
            );
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

        // Patient Policies
        if ($firstPatient) {
            $pol1 = PatientPolicy::firstOrCreate(
                ['tenant_id' => $tenant->id, 'card_number' => '01-2099384-2'],
                [
                    'patient_id' => $firstPatient->id,
                    'insurance_provider_id' => $nhif->id,
                    'insurance_scheme_id' => $nhifScheme1->id,
                    'principal_member_name' => 'Asha Juma',
                    'principal_member_number' => 'MEM-TZ-8841',
                    'relationship' => 'Self',
                    'policy_start_date' => now()->subMonths(6)->toDateString(),
                    'policy_expiry_date' => now()->addMonths(6)->toDateString(),
                    'status' => 'Active',
                    'biometric_verified' => true,
                    'verified_at' => now()->subDays(2),
                ]
            );

            // Seed Vetted Claim for Encounter 1
            if ($enc1) {
                $claim1 = InsuranceClaim::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'claim_number' => 'CLM-2026-0001'],
                    [
                        'patient_id' => $firstPatient->id,
                        'patient_policy_id' => $pol1->id,
                        'encounter_id' => $enc1->id,
                        'total_claimed_amount' => 45000.00,
                        'co_pay_amount' => 0.00,
                        'approved_amount' => 45000.00,
                        'status' => 'Vetted',
                        'scrubber_passed' => true,
                        'scrubber_errors' => null,
                    ]
                );

                InsuranceClaimItem::firstOrCreate(
                    ['insurance_claim_id' => $claim1->id, 'description' => 'General Medical Officer Outpatient Consultation'],
                    [
                        'tenant_id' => $tenant->id,
                        'item_type' => 'Consultation',
                        'item_code' => 'CON-OPD-001',
                        'quantity' => 1,
                        'unit_price' => 15000.00,
                        'claimed_amount' => 15000.00,
                        'approved_amount' => 15000.00,
                        'status' => 'Claimed',
                    ]
                );

                InsuranceClaimItem::firstOrCreate(
                    ['insurance_claim_id' => $claim1->id, 'description' => 'Full Blood Picture (FBP) Diagnostic Panel'],
                    [
                        'tenant_id' => $tenant->id,
                        'item_type' => 'Lab',
                        'item_code' => 'LAB-FBP-001',
                        'quantity' => 1,
                        'unit_price' => 15000.00,
                        'claimed_amount' => 15000.00,
                        'approved_amount' => 15000.00,
                        'status' => 'Claimed',
                    ]
                );

                InsuranceClaimItem::firstOrCreate(
                    ['insurance_claim_id' => $claim1->id, 'description' => 'Malaria Rapid Diagnostic Test (mRDT)'],
                    [
                        'tenant_id' => $tenant->id,
                        'item_type' => 'Lab',
                        'item_code' => 'LAB-MAL-001',
                        'quantity' => 1,
                        'unit_price' => 15000.00,
                        'claimed_amount' => 15000.00,
                        'approved_amount' => 15000.00,
                        'status' => 'Claimed',
                    ]
                );
            }
        }

        if ($secondPatient) {
            $pol2 = PatientPolicy::firstOrCreate(
                ['tenant_id' => $tenant->id, 'card_number' => 'JUB-TZ-884920'],
                [
                    'patient_id' => $secondPatient->id,
                    'insurance_provider_id' => $jubilee->id,
                    'insurance_scheme_id' => $jubileeScheme1->id,
                    'principal_member_name' => 'Baraka Kimaro',
                    'principal_member_number' => 'JUB-EMP-9921',
                    'relationship' => 'Self',
                    'policy_start_date' => now()->subMonths(3)->toDateString(),
                    'policy_expiry_date' => now()->addMonths(9)->toDateString(),
                    'status' => 'Active',
                    'biometric_verified' => true,
                    'verified_at' => now()->subDays(1),
                ]
            );

            // Pre-Auth
            PreAuthorization::firstOrCreate(
                ['tenant_id' => $tenant->id, 'auth_code' => 'TAR-2026-881920'],
                [
                    'patient_id' => $secondPatient->id,
                    'patient_policy_id' => $pol2->id,
                    'encounter_id' => $enc2->id ?? null,
                    'procedure_description' => 'Echocardiogram & Holter Telemetry Monitoring',
                    'requested_amount' => 120000.00,
                    'approved_amount' => 120000.00,
                    'status' => 'Approved',
                    'expires_at' => now()->addDays(30)->toDateString(),
                    'notes' => 'Approved under specialist diagnostic sub-limit by Jubilee Medical Director',
                ]
            );

            // Seed Submitted Claim for Encounter 2
            if ($enc2) {
                $claim2 = InsuranceClaim::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'claim_number' => 'CLM-2026-0002'],
                    [
                        'patient_id' => $secondPatient->id,
                        'patient_policy_id' => $pol2->id,
                        'encounter_id' => $enc2->id,
                        'total_claimed_amount' => 85000.00,
                        'co_pay_amount' => 0.00,
                        'approved_amount' => 0.00,
                        'status' => 'Submitted',
                        'batch_number' => 'BATCH-2026-AUG01',
                        'submitted_at' => now()->subDays(1),
                        'scrubber_passed' => true,
                    ]
                );

                InsuranceClaimItem::firstOrCreate(
                    ['insurance_claim_id' => $claim2->id, 'description' => 'Emergency Physician Specialized Evaluation'],
                    [
                        'tenant_id' => $tenant->id,
                        'item_type' => 'Consultation',
                        'item_code' => 'CON-SP-001',
                        'quantity' => 1,
                        'unit_price' => 35000.00,
                        'claimed_amount' => 35000.00,
                        'approved_amount' => 0.00,
                        'status' => 'Claimed',
                    ]
                );

                InsuranceClaimItem::firstOrCreate(
                    ['insurance_claim_id' => $claim2->id, 'description' => 'Serum Electrolytes & Renal Function Panel'],
                    [
                        'tenant_id' => $tenant->id,
                        'item_type' => 'Lab',
                        'item_code' => 'LAB-RFT-001',
                        'quantity' => 1,
                        'unit_price' => 25000.00,
                        'claimed_amount' => 25000.00,
                        'approved_amount' => 0.00,
                        'status' => 'Claimed',
                    ]
                );

                InsuranceClaimItem::firstOrCreate(
                    ['insurance_claim_id' => $claim2->id, 'description' => 'Female Surgical Ward Day Bed Occupancy'],
                    [
                        'tenant_id' => $tenant->id,
                        'item_type' => 'Bed',
                        'item_code' => 'BED-FSW-001',
                        'quantity' => 1,
                        'unit_price' => 25000.00,
                        'claimed_amount' => 25000.00,
                        'approved_amount' => 0.00,
                        'status' => 'Claimed',
                    ]
                );
            }
        }

        if ($thirdPatient) {
            $pol3 = PatientPolicy::firstOrCreate(
                ['tenant_id' => $tenant->id, 'card_number' => 'STR-772910'],
                [
                    'patient_id' => $thirdPatient->id,
                    'insurance_provider_id' => $strategis->id,
                    'insurance_scheme_id' => $strategisScheme1->id,
                    'principal_member_name' => 'Neema Mbowe',
                    'principal_member_number' => 'STR-IND-4410',
                    'relationship' => 'Self',
                    'policy_start_date' => now()->subMonths(1)->toDateString(),
                    'policy_expiry_date' => now()->addMonths(11)->toDateString(),
                    'status' => 'Active',
                    'biometric_verified' => true,
                    'verified_at' => now()->subDays(3),
                ]
            );

            // Seed Approved Remitted Claim for Encounter 3
            if ($enc3) {
                $claim3 = InsuranceClaim::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'claim_number' => 'CLM-2026-0003'],
                    [
                        'patient_id' => $thirdPatient->id,
                        'patient_policy_id' => $pol3->id,
                        'encounter_id' => $enc3->id,
                        'total_claimed_amount' => 45000.00,
                        'co_pay_amount' => 5000.00,
                        'approved_amount' => 40000.00,
                        'status' => 'Approved',
                        'batch_number' => 'BATCH-2026-JUL04',
                        'submitted_at' => now()->subDays(5),
                        'adjudicated_at' => now()->subDays(1),
                        'scrubber_passed' => true,
                    ]
                );

                InsuranceClaimItem::firstOrCreate(
                    ['insurance_claim_id' => $claim3->id, 'description' => 'Clinical Consultation & Metabolic Review'],
                    [
                        'tenant_id' => $tenant->id,
                        'item_type' => 'Consultation',
                        'item_code' => 'CON-OPD-001',
                        'quantity' => 1,
                        'unit_price' => 20000.00,
                        'claimed_amount' => 20000.00,
                        'approved_amount' => 20000.00,
                        'status' => 'Approved',
                    ]
                );

                InsuranceClaimItem::firstOrCreate(
                    ['insurance_claim_id' => $claim3->id, 'description' => 'Lipid Profile & Glucose Screen Panel'],
                    [
                        'tenant_id' => $tenant->id,
                        'item_type' => 'Lab',
                        'item_code' => 'LAB-LIP-001',
                        'quantity' => 1,
                        'unit_price' => 25000.00,
                        'claimed_amount' => 25000.00,
                        'approved_amount' => 20000.00,
                        'status' => 'Approved',
                    ]
                );
            }
        }

        // ----------------------------------------------------
        // SECTION 15: PROCEDURES & OPERATING THEATRE
        // ----------------------------------------------------
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

        // Seed Sample Procedure Order for Encounter 1 (Asha Juma - Dressing)
        if ($enc1) {
            $pOrder1 = ProcedureOrder::firstOrCreate(
                ['tenant_id' => $tenant->id, 'order_number' => 'PR-2026-0001'],
                [
                    'encounter_id' => $enc1->id,
                    'patient_id' => $enc1->patient_id,
                    'ordering_provider_id' => $doctor->id,
                    'procedure_catalog_id' => $proc1->id,
                    'priority' => 'Routine',
                    'clinical_indication' => 'Diabetic foot ulcer dressing with antiseptic cleansing',
                    'status' => 'Ordered',
                    'ordered_at' => now()->subHours(1),
                ]
            );
        }

        // Seed Sample Procedure Order for Encounter 2 (Baraka Kimaro - Abscess I&D)
        if ($enc2) {
            $pOrder2 = ProcedureOrder::firstOrCreate(
                ['tenant_id' => $tenant->id, 'order_number' => 'PR-2026-0002'],
                [
                    'encounter_id' => $enc2->id,
                    'patient_id' => $enc2->patient_id,
                    'ordering_provider_id' => $doctor->id,
                    'procedure_catalog_id' => $proc3->id,
                    'priority' => 'Urgent',
                    'clinical_indication' => 'Fluctuant cutaneous abscess on left forearm, tender and erythematous',
                    'status' => 'Ordered',
                    'ordered_at' => now()->subMinutes(30),
                ]
            );
        }

        // Seed Sample Surgical Case for Encounter 3 (Neema Mbowe - C-Section in OT-1)
        if ($enc3) {
            $surgOrder = ProcedureOrder::firstOrCreate(
                ['tenant_id' => $tenant->id, 'order_number' => 'PR-2026-0003'],
                [
                    'encounter_id' => $enc3->id,
                    'patient_id' => $enc3->patient_id,
                    'ordering_provider_id' => $doctor->id,
                    'procedure_catalog_id' => $proc4->id,
                    'priority' => 'Emergency',
                    'clinical_indication' => 'Fetal distress in active labour with previous scar',
                    'status' => 'InProgress',
                    'ordered_at' => now()->subHours(2),
                ]
            );

            $surgBooking = SurgicalBooking::firstOrCreate(
                ['tenant_id' => $tenant->id, 'booking_number' => 'SURG-2026-0001'],
                [
                    'procedure_order_id' => $surgOrder->id,
                    'operating_suite_id' => $suite1->id,
                    'lead_surgeon_id' => $doctor->id,
                    'anesthetist_id' => $doctor->id,
                    'scrub_nurse_id' => $nurse->id ?? $doctor->id,
                    'scheduled_start' => now()->subMinutes(45),
                    'scheduled_end' => now()->addMinutes(45),
                    'urgency' => 'Emergency',
                    'status' => 'PACU',
                ]
            );

            // WHO Checklist
            WhoSurgicalChecklist::firstOrCreate(
                ['tenant_id' => $tenant->id, 'surgical_booking_id' => $surgBooking->id],
                [
                    'sign_in_completed_at' => now()->subMinutes(40),
                    'sign_in_verified_by' => $doctor->id,
                    'time_out_completed_at' => now()->subMinutes(35),
                    'time_out_verified_by' => $doctor->id,
                    'sign_out_completed_at' => now()->subMinutes(5),
                    'sign_out_verified_by' => $doctor->id,
                    'sponge_and_needle_count_correct' => true,
                    'specimens_labeled_correctly' => true,
                ]
            );

            // PACU Score
            $matWard = Ward::where('name', 'Maternity Ward (Maternity/Postnatal)')->first();
            PacuRecoveryRecord::firstOrCreate(
                ['tenant_id' => $tenant->id, 'surgical_booking_id' => $surgBooking->id],
                [
                    'recorded_by_id' => $doctor->id,
                    'recorded_at' => now()->subMinutes(2),
                    'consciousness_score' => 2,
                    'activity_score' => 2,
                    'respiration_score' => 2,
                    'circulation_score' => 2,
                    'oxygen_saturation_score' => 2,
                    'total_aldrete_score' => 10,
                    'discharge_ready' => true,
                    'destination_ward_id' => $matWard ? $matWard->id : null,
                    'notes' => 'Patient awake, responding well, baby stable, Aldrete 10/10.',
                ]
            );
        }

        // ==============================================================
        // 16. SECTION 16: CLINICAL MORBIDITY & HOSPITAL INTELLIGENCE
        // ==============================================================
        $patients = Patient::where('tenant_id', $tenant->id)->get();
        $encounters = Encounter::where('tenant_id', $tenant->id)->get();
        $primaryDoctor = User::where('tenant_id', $tenant->id)->where('email', 'doctor@afyanova.local')->first() ?? $doctor;

        if ($encounters->isNotEmpty() && $patients->isNotEmpty()) {
            $sampleDiagnoses = [
                ['code' => 'B54', 'desc' => 'Unspecified Malaria (Malaria Kali)', 'certainty' => 'Confirmed', 'type' => 'Primary', 'weight' => 8],
                ['code' => 'I10', 'desc' => 'Essential (Primary) Hypertension', 'certainty' => 'Confirmed', 'type' => 'Primary', 'weight' => 6],
                ['code' => 'E11.9', 'desc' => 'Type 2 Diabetes Mellitus without complications', 'certainty' => 'Confirmed', 'type' => 'Primary', 'weight' => 5],
                ['code' => 'J20.9', 'desc' => 'Acute Bronchitis & Upper Respiratory Infection', 'certainty' => 'Suspected', 'type' => 'Primary', 'weight' => 4],
                ['code' => 'N39.0', 'desc' => 'Urinary Tract Infection, site not specified', 'certainty' => 'Confirmed', 'type' => 'Primary', 'weight' => 4],
                ['code' => 'A09', 'desc' => 'Infectious Gastroenteritis & Colitis', 'certainty' => 'Confirmed', 'type' => 'Primary', 'weight' => 3],
                ['code' => 'S51.9', 'desc' => 'Open Wound & Laceration of Forearm', 'certainty' => 'Confirmed', 'type' => 'Primary', 'weight' => 2],
                ['code' => 'K29.7', 'desc' => 'Gastritis, unspecified', 'certainty' => 'Confirmed', 'type' => 'Primary', 'weight' => 2],
                ['code' => 'A00.9', 'desc' => 'Cholera, unspecified (Kipindupindu)', 'certainty' => 'Confirmed', 'type' => 'Primary', 'weight' => 1],
                ['code' => 'B05.9', 'desc' => 'Measles without complication (Surua)', 'certainty' => 'Suspected', 'type' => 'Primary', 'weight' => 1],
            ];

            foreach ($sampleDiagnoses as $sDiag) {
                for ($i = 0; $i < $sDiag['weight']; $i++) {
                    $pt = $patients[$i % $patients->count()];
                    $enc = $encounters[$i % $encounters->count()];

                    Diagnosis::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'encounter_id' => $enc->id,
                            'patient_id' => $pt->id,
                            'icd_10_code' => $sDiag['code'],
                        ],
                        [
                            'description' => $sDiag['desc'],
                            'certainty' => $sDiag['certainty'],
                            'type' => $sDiag['type'],
                            'diagnosed_by' => $primaryDoctor->id,
                            'created_at' => now()->subDays(rand(0, 20)),
                        ]
                    );
                }
            }

            // Seed fast-moving dispensed stock movements
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
        ];

        $createdPermissions = [];
        foreach ($permissionsList as $p) {
            $perm = Permission::firstOrCreate(
                ['slug' => $p['slug']],
                ['name' => $p['name'], 'domain' => $p['domain']]
            );
            $createdPermissions[$p['slug']] = $perm->id;
        }

        // Standard 10 System Roles
        $standardRoles = [
            'tenant-admin' => [
                'name' => 'Tenant Administrator',
                'desc' => 'Executive administration, user provisioning, facility scoping, and audit oversight.',
                'perms' => array_keys($createdPermissions), // All except clinical note signing
            ],
            'doctor' => [
                'name' => 'Medical Officer / Clinician',
                'desc' => 'Clinical diagnosis, SOAP charting, lab ordering, Rx prescriptions, and theatre surgery.',
                'perms' => [
                    'clinical.encounter.create', 'clinical.encounter.view', 'clinical.encounter.update', 'clinical.encounter.close',
                    'clinical.encounter.override',
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
                    'scheduling.queue.view', 'scheduling.appointment.view',
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
