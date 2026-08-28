<?php

namespace App\Console\Commands;

use App\Core\Context\TenantContext;
use App\Domains\Billing\Models\CashierShift;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Billing\Models\Payment;
use App\Domains\Clinical\Models\ClinicalNote;
use App\Domains\Clinical\Models\ClinicalVital;
use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Models\User;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Patient\Models\Patient;
use App\Domains\Patient\Models\PatientContact;
use App\Domains\Patient\Models\PatientIdentifier;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class SimulateClinicCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'afya:simulate-clinic {--fresh : Wipe patient data before simulation} {--count=10 : Number of patient journeys to simulate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate full end-to-end Tanzanian clinic operations (Queue, Triage, Consult, Lab, Pharmacy, Billing, Wards)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('==============================================================');
        $this->info('  AfyaNova v3.0 — End-to-End Hospital Simulation Suite');
        $this->info('==============================================================');

        if ($this->option('fresh')) {
            $this->warn('Wiping existing patient records for fresh simulation...');
            Artisan::call('patients:clean');
        }

        $tenant = Tenant::first();
        if (! $tenant) {
            $this->error('No tenant found. Please run php artisan db:seed first.');

            return self::FAILURE;
        }

        app(TenantContext::class)->setTenantId($tenant->id);
        $facility = Facility::where('tenant_id', $tenant->id)->first();
        $doctor = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Doctor'))->first()
            ?? User::where('tenant_id', $tenant->id)->first();
        $nurse = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Nurse'))->first()
            ?? $doctor;
        $cashier = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Cashier'))->first()
            ?? $doctor;

        $count = (int) $this->option('count');
        $this->info("Simulating {$count} diverse patient clinical journeys...");

        $tanzanianProfiles = [
            [
                'first_name' => 'Juma', 'middle_name' => 'Rashid', 'last_name' => 'Bakari', 'gender' => 'Male', 'dob' => '1988-04-12', 'blood_group' => 'A+',
                'region' => 'Dar es Salaam', 'district' => 'Ilala', 'ward' => 'Kariakoo', 'phone' => '+255713456781', 'nida' => '19880412-11101-00001-21', 'nhif' => '01-9876543-1',
                'diagnosis' => 'Acute Uncomplicated Malaria', 'icd' => 'B50.9', 'service' => 'Doctor', 'priority' => 'Routine',
                'vitals' => ['temp' => 38.8, 'hr' => 96, 'sys' => 118, 'dia' => 74, 'rr' => 18, 'spo2' => 97, 'weight' => 68.5, 'height' => 174],
                'lab' => 'mRDT Malaria / Blood Smear', 'lab_result' => 'Positive (P. falciparum trophozoites ++)', 'panic' => false,
                'rx' => 'Artemether-Lumefantrine (ALu) 20/120mg',
            ],
            [
                'first_name' => 'Fatma', 'middle_name' => 'Ali', 'last_name' => 'Mwinyi', 'gender' => 'Female', 'dob' => '1995-09-24', 'blood_group' => 'O+',
                'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Msasani', 'phone' => '+255754123890', 'nida' => '19950924-21102-00002-32', 'nhif' => '01-8765432-2',
                'diagnosis' => 'Essential Primary Hypertension', 'icd' => 'I10', 'service' => 'Doctor', 'priority' => 'Routine',
                'vitals' => ['temp' => 36.6, 'hr' => 78, 'sys' => 158, 'dia' => 98, 'rr' => 16, 'spo2' => 98, 'weight' => 74.0, 'height' => 162],
                'lab' => 'Serum Creatinine & Electrolytes', 'lab_result' => 'Creatinine: 84 umol/L (Normal), K+: 4.1 mmol/L', 'panic' => false,
                'rx' => 'Amlodipine 5mg OD + Losartan 50mg OD',
            ],
            [
                'first_name' => 'Baraka', 'middle_name' => 'Emmanuel', 'last_name' => 'Mtweve', 'gender' => 'Male', 'dob' => '2019-11-05', 'blood_group' => 'B+',
                'region' => 'Arusha', 'district' => 'Arusha City', 'ward' => 'Sekei', 'phone' => '+255784998877', 'nida' => '20191105-31103-00003-43', 'nhif' => '01-7654321-3',
                'diagnosis' => 'Severe Acute Pneumonia / Hypoxemia', 'icd' => 'J18.9', 'service' => 'Triage', 'priority' => 'Emergency',
                'vitals' => ['temp' => 39.4, 'hr' => 138, 'sys' => 95, 'dia' => 60, 'rr' => 44, 'spo2' => 89, 'weight' => 16.0, 'height' => 102],
                'lab' => 'Full Blood Picture (FBP)', 'lab_result' => 'WBC: 22.4 x10^9/L (Leukocytosis), Hb: 6.2 g/dL', 'panic' => true,
                'rx' => 'Ceftriaxone 500mg IV BD + Oxygen Therapy',
            ],
            [
                'first_name' => 'Neema', 'middle_name' => 'Joseph', 'last_name' => 'Mrema', 'gender' => 'Female', 'dob' => '1992-02-18', 'blood_group' => 'O-',
                'region' => 'Dodoma', 'district' => 'Dodoma City', 'ward' => 'Kikuyu Kaskazini', 'phone' => '+255762334455', 'nida' => '19920218-41104-00004-54', 'nhif' => '01-6543210-4',
                'diagnosis' => 'Antenatal Care Routine Assessment (28 Weeks)', 'icd' => 'Z34.8', 'service' => 'Triage', 'priority' => 'Routine',
                'vitals' => ['temp' => 36.7, 'hr' => 82, 'sys' => 112, 'dia' => 70, 'rr' => 16, 'spo2' => 99, 'weight' => 64.0, 'height' => 160],
                'lab' => 'Urine Dipstick Routine & Blood Grouping', 'lab_result' => 'Protein: Negative, Glucose: Negative, Rh: O Negative', 'panic' => false,
                'rx' => 'Ferrous Sulphate 200mg + Folic Acid 5mg OD',
            ],
            [
                'first_name' => 'Daudi', 'middle_name' => 'Kassim', 'last_name' => 'Mwamba', 'gender' => 'Male', 'dob' => '1975-06-30', 'blood_group' => 'AB+',
                'region' => 'Mwanza', 'district' => 'Nyamagana', 'ward' => 'Isamilo', 'phone' => '+255719887766', 'nida' => '19750630-51105-00005-65', 'nhif' => '01-5432109-5',
                'diagnosis' => 'Acute Laceration Right Forearm (Minor Trauma)', 'icd' => 'S51.8', 'service' => 'Procedure', 'priority' => 'Urgent',
                'vitals' => ['temp' => 36.9, 'hr' => 86, 'sys' => 128, 'dia' => 82, 'rr' => 18, 'spo2' => 98, 'weight' => 81.0, 'height' => 178],
                'lab' => null, 'lab_result' => null, 'panic' => false,
                'rx' => 'Tetanus Toxoid 0.5ml IM + Amoxicillin/Clav 625mg BD',
            ],
            [
                'first_name' => 'Aisha', 'middle_name' => 'Hassan', 'last_name' => 'Kipenzi', 'gender' => 'Female', 'dob' => '2001-12-14', 'blood_group' => 'A-',
                'region' => 'Dar es Salaam', 'district' => 'Temeke', 'ward' => 'Mbagala', 'phone' => '+255715990011', 'nida' => '20011214-61106-00006-76', 'nhif' => '01-4321098-6',
                'diagnosis' => 'Acute Infectious Gastroenteritis & Dehydration', 'icd' => 'A09', 'service' => 'Lab', 'priority' => 'Urgent',
                'vitals' => ['temp' => 37.9, 'hr' => 104, 'sys' => 102, 'dia' => 64, 'rr' => 20, 'spo2' => 97, 'weight' => 52.0, 'height' => 158],
                'lab' => 'Stool Microscopy Routine & Wet Prep', 'lab_result' => 'Pus cells 10-15/HPF, Entamoeba histolytica cysts seen', 'panic' => false,
                'rx' => 'Metronidazole 400mg TDS for 7 days + ORS Sachets',
            ],
            [
                'first_name' => 'Emmanuel', 'middle_name' => 'Lucas', 'last_name' => 'Massawe', 'gender' => 'Male', 'dob' => '1963-08-08', 'blood_group' => 'O+',
                'region' => 'Kilimanjaro', 'district' => 'Moshi Urban', 'ward' => 'Kiboriloni', 'phone' => '+255787112233', 'nida' => '19630808-71107-00007-87', 'nhif' => '01-3210987-7',
                'diagnosis' => 'Type 2 Diabetes Mellitus with Peripheral Neuropathy', 'icd' => 'E11.4', 'service' => 'Cashier', 'priority' => 'Routine',
                'vitals' => ['temp' => 36.5, 'hr' => 74, 'sys' => 134, 'dia' => 84, 'rr' => 16, 'spo2' => 98, 'weight' => 79.5, 'height' => 171],
                'lab' => 'Fasting Blood Glucose & HbA1c', 'lab_result' => 'FBG: 11.8 mmol/L, HbA1c: 9.4%', 'panic' => false,
                'rx' => 'Metformin 850mg BD + Glimepiride 2mg OD',
            ],
            [
                'first_name' => 'Zawadi', 'middle_name' => 'Peter', 'last_name' => 'Shao', 'gender' => 'Female', 'dob' => '1984-05-19', 'blood_group' => 'B-',
                'region' => 'Mbeya', 'district' => 'Mbeya City', 'ward' => 'Iyunga', 'phone' => '+255755443322', 'nida' => '19840519-81108-00008-98', 'nhif' => '01-2109876-8',
                'diagnosis' => 'Acute Suppurative Otitis Media Right Ear', 'icd' => 'H66.0', 'service' => 'Pharmacy', 'priority' => 'Routine',
                'vitals' => ['temp' => 37.4, 'hr' => 80, 'sys' => 122, 'dia' => 76, 'rr' => 16, 'spo2' => 99, 'weight' => 63.0, 'height' => 165],
                'lab' => 'Ear Swab Gram Stain & Culture', 'lab_result' => 'Gram-positive cocci in pairs (Streptococcus pneumoniae)', 'panic' => false,
                'rx' => 'Amoxicillin/Clavulanate 1g BD + Ciprofloxacin Ear Drops',
            ],
        ];

        // Ensure Active Cashier Shift exists for billing transactions
        $shift = CashierShift::where('tenant_id', $tenant->id)->where('status', 'Open')->first();
        if (! $shift) {
            $shift = CashierShift::create([
                'tenant_id' => $tenant->id,
                'facility_id' => $facility?->id,
                'user_id' => $cashier->id,
                'till_number' => 'TILL-01',
                'opening_float' => 50000,
                'status' => 'Open',
                'opened_at' => Carbon::now()->subHours(4),
            ]);
        }

        $createdPatients = [];
        $simulatedCount = min($count, count($tanzanianProfiles));

        $baseSeq = Patient::where('tenant_id', $tenant->id)->count() + 1;

        for ($i = 0; $i < $simulatedCount; $i++) {
            $p = $tanzanianProfiles[$i];

            // 1. Create Patient Record
            $mrn = 'MRN-'.date('Y').'-'.str_pad($baseSeq + $i + rand(100, 999), 5, '0', STR_PAD_LEFT);
            $patient = Patient::create([
                'tenant_id' => $tenant->id,
                'primary_mrn' => $mrn,
                'first_name' => $p['first_name'],
                'middle_name' => $p['middle_name'],
                'last_name' => $p['last_name'],
                'dob' => $p['dob'],
                'gender' => $p['gender'],
                'blood_group' => $p['blood_group'],
                'marital_status' => 'Married',
                'nationality' => 'Tanzanian',
                'region' => $p['region'],
                'district' => $p['district'],
                'ward' => $p['ward'],
                'status' => 'Active',
            ]);

            // Contacts & Identifiers
            PatientContact::create([
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'contact_type' => 'phone',
                'value' => $p['phone'],
                'is_verified' => true,
            ]);

            $nidaVal = substr($p['nida'], 0, 16).'-'.str_pad($baseSeq + $i + rand(10, 99), 4, '0', STR_PAD_LEFT);
            $nhifVal = '01-'.str_pad($baseSeq + $i + rand(1000, 9999), 7, '0', STR_PAD_LEFT).'-'.rand(1, 9);

            PatientIdentifier::create([
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'type' => 'NIDA',
                'identifier_value' => $nidaVal,
                'is_primary' => true,
            ]);

            PatientIdentifier::create([
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'type' => 'NHIF',
                'identifier_value' => $nhifVal,
                'is_primary' => false,
            ]);

            // 2. Create Active Queue Ticket
            $ticketNumber = strtoupper(substr($p['service'], 0, 1)).'-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT);
            $queueTicket = QueueTicket::create([
                'tenant_id' => $tenant->id,
                'facility_id' => $facility?->id,
                'patient_id' => $patient->id,
                'ticket_number' => $ticketNumber,
                'service_point' => $p['service'],
                'current_service_point' => $p['service'],
                'status' => $i === 0 ? 'In Progress' : 'Waiting',
                'priority' => $p['priority'],
                'visit_type' => 'OPD Consultation',
                'created_at' => Carbon::now()->subMinutes(rand(5, 120)),
            ]);

            // 3. Create Clinical Encounter
            $encounter = Encounter::create([
                'tenant_id' => $tenant->id,
                'facility_id' => $facility?->id,
                'patient_id' => $patient->id,
                'provider_id' => $doctor->id,
                'encounter_type' => 'OPD Consultation',
                'status' => $i === 0 ? 'In Progress' : 'Completed',
                'started_at' => Carbon::now()->subMinutes(rand(20, 90)),
            ]);

            // 4. Record Clinical Vitals
            $v = $p['vitals'];
            $heightM = $v['height'] / 100;
            $bmi = round($v['weight'] / ($heightM * $heightM), 1);
            ClinicalVital::create([
                'tenant_id' => $tenant->id,
                'facility_id' => $facility?->id,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'recorded_by' => $nurse->id,
                'temperature_c' => $v['temp'],
                'heart_rate' => $v['hr'],
                'systolic_bp' => $v['sys'],
                'diastolic_bp' => $v['dia'],
                'respiratory_rate' => $v['rr'],
                'oxygen_saturation' => $v['spo2'],
                'weight_kg' => $v['weight'],
                'height_cm' => $v['height'],
                'bmi' => $bmi,
                'recorded_at' => Carbon::now()->subMinutes(rand(15, 60)),
            ]);

            // 5. Create SOAP Clinical Note & Diagnosis
            ClinicalNote::create([
                'tenant_id' => $tenant->id,
                'facility_id' => $facility?->id,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'author_id' => $doctor->id,
                'note_type' => 'SOAP',
                'content' => [
                    'subjective' => "Patient presents with symptoms consistent with {$p['diagnosis']}. Denies severe complications.",
                    'objective' => "Febrile / Vitals: BP {$v['sys']}/{$v['dia']} mmHg, HR {$v['hr']} bpm, Temp {$v['temp']}°C, SpO2 {$v['spo2']}%. Chest clear, abdomen soft non-tender.",
                    'assessment' => "{$p['diagnosis']} (ICD-10: {$p['icd']})",
                    'plan' => "1. {$p['rx']}\n2. Review after 3 days or return immediately if danger signs appear.",
                ],
                'is_signed' => true,
                'signed_by' => $doctor->id,
                'signed_at' => Carbon::now()->subMinutes(10),
            ]);

            Diagnosis::create([
                'tenant_id' => $tenant->id,
                'encounter_id' => $encounter->id,
                'patient_id' => $patient->id,
                'diagnosed_by' => $doctor->id,
                'icd_10_code' => $p['icd'],
                'description' => $p['diagnosis'],
                'certainty' => 'Confirmed',
                'type' => 'Primary',
            ]);

            // 6. Lab Orders & Panic Alert Simulation
            if ($p['lab']) {
                $labTest = LabTest::where('tenant_id', $tenant->id)->where('name', 'like', "%{$p['lab']}%")->first()
                    ?? LabTest::create([
                        'tenant_id' => $tenant->id,
                        'test_code' => 'LT-'.rand(100, 999),
                        'name' => $p['lab'],
                        'category' => 'Hematology & Parasitology',
                        'specimen_type' => 'Whole Blood (EDTA)',
                        'price' => 20000,
                        'turnaround_time_minutes' => 30,
                        'is_active' => true,
                        'parameters' => [['name' => 'Result', 'unit' => '', 'critical_value' => 'Positive']],
                    ]);

                $labOrder = LabOrder::create([
                    'tenant_id' => $tenant->id,
                    'facility_id' => $facility?->id,
                    'patient_id' => $patient->id,
                    'encounter_id' => $encounter->id,
                    'ordered_by' => $doctor->id,
                    'status' => 'Completed',
                    'order_number' => 'LAB-'.date('ymd').'-'.str_pad($baseSeq + $i + rand(10, 99), 4, '0', STR_PAD_LEFT),
                    'clinical_notes' => "Rule out {$p['diagnosis']}",
                ]);

                LabOrderItem::create([
                    'tenant_id' => $tenant->id,
                    'lab_order_id' => $labOrder->id,
                    'lab_test_id' => $labTest->id,
                    'price' => $labTest->price,
                    'status' => 'Verified',
                    'specimen_barcode' => 'BAR-'.rand(10000, 99999),
                    'results' => [['name' => 'Result', 'value' => $p['lab_result']]],
                    'has_critical_value' => $p['panic'],
                    'verified_by_id' => $doctor->id,
                ]);
            }

            // 7. Billing Invoice & Payment Settlement
            $consultationFee = 15000;
            $labFee = $p['lab'] ? 20000 : 0;
            $rxFee = 10000;
            $totalAmount = $consultationFee + $labFee + $rxFee;

            $invoice = Invoice::create([
                'tenant_id' => $tenant->id,
                'facility_id' => $facility?->id,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'invoice_number' => 'INV-'.date('Ymd').'-'.str_pad($baseSeq + $i + rand(100, 999), 5, '0', STR_PAD_LEFT),
                'total_amount' => $totalAmount,
                'paid_amount' => $totalAmount,
                'balance' => 0,
                'status' => 'Paid',
                'payment_mode' => $i % 2 === 0 ? 'Cash' : 'M-Pesa (Lipa Namba)',
            ]);

            InvoiceLineItem::create([
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'description' => 'General Medical Consultation',
                'category' => 'Consultation',
                'quantity' => 1,
                'unit_price' => $consultationFee,
                'total_price' => $consultationFee,
            ]);

            if ($labFee > 0) {
                InvoiceLineItem::create([
                    'tenant_id' => $tenant->id,
                    'invoice_id' => $invoice->id,
                    'description' => $p['lab'],
                    'category' => 'Laboratory',
                    'quantity' => 1,
                    'unit_price' => $labFee,
                    'total_price' => $labFee,
                ]);
            }

            Payment::create([
                'tenant_id' => $tenant->id,
                'facility_id' => $facility?->id,
                'invoice_id' => $invoice->id,
                'user_id' => $cashier->id,
                'cashier_shift_id' => $shift->id,
                'receipt_number' => 'REC-'.date('Ymd').'-'.str_pad($baseSeq + $i + rand(100, 999), 5, '0', STR_PAD_LEFT),
                'amount' => $totalAmount,
                'payment_method' => $i % 2 === 0 ? 'Cash' : 'Mobile Money',
                'transaction_reference' => $i % 2 === 0 ? 'CASH-'.rand(1000, 9999) : 'MPESA'.strtoupper(Str::random(8)),
                'status' => 'Completed',
            ]);

            // 8. Inpatient Ward Admission (for severe case: Baraka Mtweve)
            if ($p['panic']) {
                $ward = Ward::where('tenant_id', $tenant->id)->first();
                $bed = Bed::where('tenant_id', $tenant->id)->where('status', 'Available')->first();
                if ($ward && $bed) {
                    $bed->update(['status' => 'Occupied']);
                    Admission::create([
                        'tenant_id' => $tenant->id,
                        'facility_id' => $facility?->id,
                        'patient_id' => $patient->id,
                        'encounter_id' => $encounter->id,
                        'ward_id' => $ward->id,
                        'bed_id' => $bed->id,
                        'admitting_doctor_id' => $doctor->id,
                        'admission_number' => 'ADM-'.date('Y').'-'.str_pad($baseSeq + $i + rand(100, 999), 5, '0', STR_PAD_LEFT),
                        'admission_reason' => 'Severe Pneumonia with Desaturation & Anemia',
                        'provisional_diagnosis' => $p['diagnosis'],
                        'status' => 'Admitted',
                        'admitted_at' => Carbon::now()->subHours(2),
                    ]);
                }
            }

            $createdPatients[] = "{$patient->first_name} {$patient->last_name} ({$mrn}) — {$p['diagnosis']}";
        }

        $this->info("✓ Successfully created {$simulatedCount} full patient journeys.");
        foreach ($createdPatients as $line) {
            $this->line("  → {$line}");
        }

        $this->info('');
        $this->info('✓ Queue Tickets active across Triage, Doctor, Lab, Pharmacy, and Cashier.');
        $this->info('✓ Panic lab value alerts generated for emergency siren & red flag testing.');
        $this->info('✓ Invoices settled and linked to 80mm ESC/POS thermal printing.');
        $this->info('✓ Patient admitted to ward with bedside wristband ready for printing.');
        $this->info('==============================================================');

        return self::SUCCESS;
    }
}
