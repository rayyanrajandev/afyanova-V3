<?php

use App\Domains\Billing\Actions\ApplyDepositToInvoiceAction;
use App\Domains\Billing\Actions\RecordPatientDepositAction;
use App\Domains\Billing\Actions\RecordPaymentAction;
use App\Domains\Billing\Exceptions\LedgerImbalanceException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\LedgerTransaction;
use App\Domains\Billing\Models\Payment;
use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use App\Domains\Insurance\Actions\ProcessRemittanceAdviceAction;
use App\Domains\Insurance\Actions\SplitInvoiceForInsuranceAction;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Insurance\Models\InsuranceProvider;
use App\Domains\Insurance\Models\InsuranceScheme;
use App\Domains\Insurance\Models\PatientPolicy;
use App\Domains\Inventory\Actions\ConfirmStockTransferAction;
use App\Domains\Inventory\Actions\CreateStockTransferAction;
use App\Domains\Inventory\Actions\ProcessGoodsReceiptAction;
use App\Domains\Inventory\Actions\RecordBlindStocktakeCountAction;
use App\Domains\Inventory\Actions\RecordDdaAdministrationAction;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StocktakeSession;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use Ramsey\Uuid\Uuid;

test('invoice payment enforces pessimistic locking, creates payment receipt, and rejects overpayments', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-PAY-001',
        'first_name' => 'Payment',
        'last_name' => 'Tester',
        'dob' => '1992-05-10',
        'gender' => 'male',
        'status' => 'Active',
    ]);

    $invoice = Invoice::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'invoice_number' => 'INV-2026-TEST01',
        'total_amount' => 100000.00,
        'paid_amount' => 0.00,
        'status' => 'Pending',
        'issued_at' => now(),
    ]);

    $payAction = app(RecordPaymentAction::class);

    // 1. Partial Payment of 40,000 TZS via M-Pesa
    $tx1 = $payAction->execute($invoice, 40000.00, 'M-Pesa', 'MPESA123456');

    expect($invoice->fresh()->paid_amount)->toEqual(40000.00)
        ->and($invoice->fresh()->status)->toBe('Partially Paid');

    $paymentReceipt1 = Payment::where('invoice_id', $invoice->id)->latest()->first();
    expect($paymentReceipt1)->not->toBeNull()
        ->and($paymentReceipt1->amount)->toEqual(40000.00)
        ->and($paymentReceipt1->payment_method)->toBe('M-Pesa');

    // 2. Reject Overpayment (attempting 70,000 when only 60,000 is remaining)
    expect(fn () => $payAction->execute($invoice->fresh(), 70000.00, 'Cash'))
        ->toThrow(LedgerImbalanceException::class);

    // 3. Complete settlement of remaining 60,000 TZS via Cash
    $tx2 = $payAction->execute($invoice->fresh(), 60000.00, 'Cash');

    expect($invoice->fresh()->paid_amount)->toEqual(100000.00)
        ->and($invoice->fresh()->status)->toBe('Paid');

    // 4. Any further payment on a fully paid invoice must be rejected as overpayment
    expect(fn () => $payAction->execute($invoice->fresh(), 5000.00, 'Cash'))
        ->toThrow(LedgerImbalanceException::class);
});

test('patient advance deposit recording and drawdown against outstanding invoice', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-DEP-001',
        'first_name' => 'Deposit',
        'last_name' => 'Patient',
        'dob' => '1985-11-20',
        'gender' => 'female',
        'status' => 'Active',
    ]);

    // 1. Record Patient Deposit of 250,000 TZS
    $depositAction = app(RecordPatientDepositAction::class);
    $deposit = $depositAction->execute($patient, 250000.00, 'Bank POS', 'POS-SLIP-999', 'Elective Surgery Deposit');

    expect($deposit)->not->toBeNull()
        ->and($deposit->amount)->toEqual(250000.00)
        ->and($deposit->balance_remaining)->toEqual(250000.00)
        ->and($deposit->status)->toBe('Active');

    // Verify Deposit GL Transaction: Debit Cash/Bank, Credit Patient Deposit Liability
    $depositTx = LedgerTransaction::where('reference_id', $deposit->id)->first();
    expect($depositTx)->not->toBeNull();
    $debitEntry = $depositTx->entries()->where('debit', '>', 0)->first();
    $creditEntry = $depositTx->entries()->where('credit', '>', 0)->first();
    expect($debitEntry->account->code)->toBe('1030') // Bank POS Asset
        ->and($creditEntry->account->code)->toBe('2100'); // Deposit Liability

    // 2. Issue an Invoice for 150,000 TZS
    $invoice = Invoice::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'invoice_number' => 'INV-2026-SURG01',
        'total_amount' => 150000.00,
        'paid_amount' => 0.00,
        'status' => 'Pending',
        'issued_at' => now(),
    ]);

    // 3. Drawdown deposit against invoice
    $applyAction = app(ApplyDepositToInvoiceAction::class);
    $allocation = $applyAction->execute($deposit, $invoice, 150000.00);

    expect($allocation->allocated_amount)->toEqual(150000.00)
        ->and($deposit->fresh()->balance_remaining)->toEqual(100000.00)
        ->and($deposit->fresh()->status)->toBe('Active')
        ->and($invoice->fresh()->paid_amount)->toEqual(150000.00)
        ->and($invoice->fresh()->status)->toBe('Paid');
});

test('insurance co-pay splitting creates patient co-pay invoice alongside insurance claim', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-INS-001',
        'first_name' => 'Insured',
        'last_name' => 'Patient',
        'dob' => '1980-03-25',
        'gender' => 'male',
        'status' => 'Active',
    ]);

    $provider = InsuranceProvider::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'name' => 'National Health Insurance Fund',
        'code' => 'NHIF-TZ',
        'is_active' => true,
    ]);

    $scheme = InsuranceScheme::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'insurance_provider_id' => $provider->id,
        'name' => 'NHIF Comprehensive',
        'code' => 'NHIF-COMP',
        'co_pay_type' => 'FixedAmount',
        'co_pay_amount' => 10000.00, // 10,000 TZS fixed co-pay
        'is_active' => true,
    ]);

    $policy = PatientPolicy::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'insurance_provider_id' => $provider->id,
        'insurance_scheme_id' => $scheme->id,
        'card_number' => 'NHIF-CARD-99999',
        'policy_number' => 'NHIF-POL-99999',
        'status' => 'Active',
        'policy_start_date' => now()->subMonths(6),
        'policy_expiry_date' => now()->addMonths(6),
    ]);

    $encounter = Encounter::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'provider_id' => $user->id,
        'encounter_type' => 'OPD',
        'status' => 'Completed',
        'start_time' => now(),
    ]);

    Diagnosis::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'diagnosed_by' => $user->id,
        'icd10_code' => 'B54',
        'description' => 'Unspecified malaria',
        'diagnosis_type' => 'Primary',
        'status' => 'Active',
    ]);

    $splitAction = app(SplitInvoiceForInsuranceAction::class);
    $splitResult = $splitAction->execute($encounter, $policy);

    expect($splitResult['insurance_claim'])->not->toBeNull()
        ->and($splitResult['co_pay_amount'])->toEqual(10000.00)
        ->and($splitResult['co_pay_invoice'])->not->toBeNull()
        ->and($splitResult['co_pay_invoice']->total_amount)->toEqual(10000.00);
});

test('batch insurance remittance advice settles claims, posts disallowances, and balances general ledger', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-REMIT-001',
        'first_name' => 'Remittance',
        'last_name' => 'Subject',
        'dob' => '1991-01-01',
        'gender' => 'female',
        'status' => 'Active',
    ]);

    $provider = InsuranceProvider::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'name' => 'AAR Insurance',
        'code' => 'AAR-TZ',
        'is_active' => true,
    ]);

    $policy = PatientPolicy::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'insurance_provider_id' => $provider->id,
        'card_number' => 'AAR-CARD-1010',
        'policy_number' => 'AAR-POL-1010',
        'status' => 'Active',
    ]);

    $encounter = Encounter::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'provider_id' => $user->id,
        'encounter_type' => 'OPD',
        'status' => 'Completed',
        'start_time' => now(),
    ]);

    $claim1 = InsuranceClaim::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'encounter_id' => $encounter->id,
        'claim_number' => 'CLM-2026-001',
        'patient_id' => $patient->id,
        'patient_policy_id' => $policy->id,
        'total_claimed_amount' => 50000.00,
        'approved_amount' => 50000.00,
        'status' => 'Submitted',
    ]);

    $claim2 = InsuranceClaim::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'encounter_id' => $encounter->id,
        'claim_number' => 'CLM-2026-002',
        'patient_id' => $patient->id,
        'patient_policy_id' => $policy->id,
        'total_claimed_amount' => 30000.00,
        'approved_amount' => 30000.00,
        'status' => 'Submitted',
    ]);

    $remitAction = app(ProcessRemittanceAdviceAction::class);

    // Claim 1: Paid in full (50,000)
    // Claim 2: Partial settlement of 20,000 + 10,000 Disallowed (Tariff exceeded)
    $remittance = $remitAction->execute(
        $provider,
        'EFT-AAR-2026-888',
        now()->toDateString(),
        [
            [
                'claim_id' => $claim1->id,
                'settled_amount' => 50000.00,
                'disallowed_amount' => 0.00,
            ],
            [
                'claim_id' => $claim2->id,
                'settled_amount' => 20000.00,
                'disallowed_amount' => 10000.00,
                'reason_code' => 'TARIFF_EXCEEDED',
                'remarks' => 'Price capped to agreed scheme tariff',
            ],
        ]
    );

    expect($remittance->total_claimed_amount)->toEqual(80000.00)
        ->and($remittance->total_settled_amount)->toEqual(70000.00)
        ->and($remittance->total_disallowed_amount)->toEqual(10000.00)
        ->and($claim1->fresh()->status)->toBe('Paid')
        ->and($claim2->fresh()->status)->toBe('Partially Paid');

    // Verify GL Transaction Balancing:
    // Debit Bank: 70,000 | Debit Disallowance: 10,000 | Credit Insurance AR: 80,000
    $tx = LedgerTransaction::where('reference_id', $remittance->id)->first();
    expect($tx)->not->toBeNull();
    $totalDebits = $tx->entries()->sum('debit');
    $totalCredits = $tx->entries()->sum('credit');
    expect($totalDebits)->toEqual(80000.00)
        ->and($totalCredits)->toEqual(80000.00);
});

test('dda narcotic administration requires two distinct clinicians and deducts physical batch balance', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $witnessUser = User::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'first_name' => 'Witness',
        'last_name' => 'Pharmacist',
        'email' => 'witness@afyanova.com',
        'password_hash' => bcrypt('Password123!'),
        'status' => 'Active',
    ]);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-DDA-001',
        'first_name' => 'DDA',
        'last_name' => 'Patient',
        'dob' => '1984-06-12',
        'gender' => 'male',
        'status' => 'Active',
    ]);

    $morphine = MedicationFormulary::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'code' => 'MED-MOR-10',
        'generic_name' => 'Morphine Sulfate',
        'drug_class' => 'Opioid',
        'strength' => '10mg/ml',
        'form' => 'Ampoule',
        'route' => 'Intravenous',
        'is_active' => true,
    ]);

    $itemMaster = ItemMaster::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'item_code' => 'DDA-MOR-10',
        'name' => 'Morphine Sulfate 10mg/ml Injection',
        'category' => 'Dangerous_Drug',
        'medication_id' => $morphine->id,
        'is_active' => true,
    ]);

    $batch = InventoryBatch::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'medication_id' => $morphine->id,
        'batch_number' => 'DDA-BATCH-001',
        'initial_quantity' => 20,
        'current_quantity' => 20,
        'unit_cost' => 5000.00,
        'unit_selling_price' => 8000.00,
        'expiry_date' => now()->addYear(),
        'status' => 'Active',
    ]);

    $ddaAction = app(RecordDdaAdministrationAction::class);

    // 1. Invariant: Attempting DDA administration with same user as nurse & witness must be rejected
    expect(fn () => $ddaAction->execute(
        $env['facility']->id,
        $itemMaster->id,
        $batch->id,
        1.0,
        0.0,
        null,
        $patient->id,
        $user->id,
        $user->id,
        $user->id, // Same user as witness
        'Severe acute post-operative pain'
    ))->toThrow(InvalidArgumentException::class);

    // 2. Valid administration with two distinct clinicians
    $log = $ddaAction->execute(
        $env['facility']->id,
        $itemMaster->id,
        $batch->id,
        1.0,
        0.0,
        null,
        $patient->id,
        $user->id,
        $user->id,
        $witnessUser->id,
        'Severe acute post-operative pain'
    );

    expect($log)->not->toBeNull()
        ->and($log->balance_before)->toEqual(20.00)
        ->and($log->balance_after)->toEqual(19.00)
        ->and($batch->fresh()->current_quantity)->toBe(19);
});

test('goods receipt recalculates moving average cost avco and balances accounts payable ledger', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $supplier = Supplier::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'name' => 'Medical Stores Department (MSD)',
        'code' => 'SUP-MSD-01',
        'supplier_code' => 'SUP-MSD-01',
        'is_active' => true,
    ]);

    $location = InventoryLocation::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'code' => 'LOC-MAIN-01',
        'name' => 'Main Pharmacy Store',
        'location_type' => 'Main_Store',
        'is_dispensing_enabled' => true,
        'is_active' => true,
    ]);

    $med = MedicationFormulary::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'code' => 'MED-CIPRO-500',
        'generic_name' => 'Ciprofloxacin',
        'drug_class' => 'Fluoroquinolone',
        'strength' => '500mg',
        'form' => 'Tablet',
        'route' => 'Oral',
        'is_active' => true,
    ]);

    $item = ItemMaster::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'item_code' => 'ITM-CIP-500',
        'name' => 'Ciprofloxacin 500mg Tablets',
        'category' => 'Pharmaceutical',
        'medication_id' => $med->id,
        'unit_cost_price' => 100.00, // Initial AVCO: 100 TZS
        'is_active' => true,
    ]);

    $grnAction = app(ProcessGoodsReceiptAction::class);

    // Receive 200 units at 150 TZS each (Total 30,000 TZS)
    $grn = $grnAction->execute(
        null,
        $supplier->id,
        $env['facility']->id,
        $location->id,
        [
            [
                'medication_id' => $med->id,
                'batch_number' => 'CIP-B100',
                'expiry_date' => now()->addYears(2)->toDateString(),
                'received_quantity' => 200,
                'unit_purchase_cost' => 150.00,
                'unit_selling_price' => 200.00,
            ],
        ],
        now()->toDateString(),
        'INV-MSD-9988',
        'DEL-MSD-9988'
    );

    expect($grn->total_received_value)->toEqual(30000.00);

    // AVCO updated on ItemMaster
    expect($item->fresh()->unit_cost_price)->toEqual(150.00);

    // Verify GL: Debit Inventory Asset (1300), Credit Accounts Payable (2000)
    $tx = LedgerTransaction::where('reference_id', $grn->id)->first();
    expect($tx)->not->toBeNull();
    $debitEntry = $tx->entries()->where('debit', '>', 0)->first();
    $creditEntry = $tx->entries()->where('credit', '>', 0)->first();
    expect($debitEntry->account->code)->toBe('1300')
        ->and($creditEntry->account->code)->toBe('2000')
        ->and($debitEntry->debit)->toEqual(30000.00)
        ->and($creditEntry->credit)->toEqual(30000.00);
});

test('two-step inter-store stock transfer handles in-transit quarantine and confirmation', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $mainStore = InventoryLocation::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'code' => 'LOC-CENTRAL-01',
        'name' => 'Central Warehouse',
        'location_type' => 'Main_Store',
        'is_dispensing_enabled' => false,
        'is_active' => true,
    ]);

    $dispensary = InventoryLocation::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'code' => 'LOC-DISP-01',
        'name' => 'OPD Dispensary',
        'location_type' => 'Pharmacy_Dispensing',
        'is_dispensing_enabled' => true,
        'is_active' => true,
    ]);

    $med = MedicationFormulary::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'code' => 'MED-PCM-500',
        'generic_name' => 'Paracetamol',
        'drug_class' => 'Analgesic',
        'strength' => '500mg',
        'form' => 'Tablet',
        'route' => 'Oral',
        'is_active' => true,
    ]);

    $batch = InventoryBatch::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'medication_id' => $med->id,
        'batch_number' => 'PCM-TRF-01',
        'initial_quantity' => 100,
        'current_quantity' => 100,
        'unit_cost' => 50.00,
        'expiry_date' => now()->addYears(2),
        'status' => 'Active',
    ]);

    // Initial stock in Main Store = 100
    InventoryStockBalance::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'location_id' => $mainStore->id,
        'medication_id' => $med->id,
        'batch_id' => $batch->id,
        'quantity_on_hand' => 100,
    ]);

    // 1. Dispatch 40 units (Step 1: In-Transit)
    $transfer = app(CreateStockTransferAction::class)->execute(
        $mainStore->id,
        $dispensary->id,
        [
            ['medication_id' => $med->id, 'batch_id' => $batch->id, 'quantity' => 40],
        ]
    );

    expect($transfer->status)->toBe('Dispatched_In_Transit');
    $sourceBal = InventoryStockBalance::where('location_id', $mainStore->id)->where('batch_id', $batch->id)->first();
    expect($sourceBal->quantity_on_hand)->toBe(60);

    // 2. Receive and Confirm Transfer at Destination (Step 2: Confirm)
    $confirmed = app(ConfirmStockTransferAction::class)->execute($transfer->id);

    expect($confirmed->status)->toBe('Received_Confirmed');
    $destBal = InventoryStockBalance::where('location_id', $dispensary->id)->where('batch_id', $batch->id)->first();
    expect($destBal->quantity_on_hand)->toBe(40);
});

test('blind stocktake counting and reconciliation posts inventory shrinkage ledger entry', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $location = InventoryLocation::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'code' => 'LOC-WARD2-01',
        'name' => 'Ward 2 Drug Cupboard',
        'location_type' => 'Ward_Stock',
        'is_dispensing_enabled' => true,
        'is_active' => true,
    ]);

    $med = MedicationFormulary::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'code' => 'MED-AMX-500',
        'generic_name' => 'Amoxicillin',
        'drug_class' => 'Penicillin',
        'strength' => '500mg',
        'form' => 'Capsule',
        'route' => 'Oral',
        'is_active' => true,
    ]);

    $batch = InventoryBatch::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'medication_id' => $med->id,
        'batch_number' => 'AMX-STK-01',
        'initial_quantity' => 50,
        'current_quantity' => 50,
        'unit_cost' => 200.00,
        'expiry_date' => now()->addYear(),
        'status' => 'Active',
    ]);

    InventoryStockBalance::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'location_id' => $location->id,
        'medication_id' => $med->id,
        'batch_id' => $batch->id,
        'quantity_on_hand' => 50,
    ]);

    $session = StocktakeSession::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'location_id' => $location->id,
        'session_number' => 'STK-2026-001',
        'session_type' => 'Blind_Cycle_Count',
        'status' => 'In_Progress',
        'initiated_by' => $user->id,
    ]);

    // Blind Count reveals 45 physical units (5 units shrinkage @ 200 = 1,000 TZS loss)
    $blindAction = app(RecordBlindStocktakeCountAction::class);
    $reconciledSession = $blindAction->execute($session, [
        [
            'medication_id' => $med->id,
            'batch_id' => $batch->id,
            'physical_counted_quantity' => 45,
            'variance_reason' => 'Physical breakage during handling',
        ],
    ]);

    expect($reconciledSession->status)->toBe('Approved_Reconciled');
    $bal = InventoryStockBalance::where('location_id', $location->id)->where('batch_id', $batch->id)->first();
    expect($bal->quantity_on_hand)->toBe(45);

    // Verify Shrinkage GL Entry: Debit Shrinkage Expense (5200) 1000, Credit Inventory Asset (1300) 1000
    $tx = LedgerTransaction::where('reference_id', $session->id)->first();
    expect($tx)->not->toBeNull();
    $debitEntry = $tx->entries()->where('debit', '>', 0)->first();
    $creditEntry = $tx->entries()->where('credit', '>', 0)->first();
    expect($debitEntry->account->code)->toBe('5200')
        ->and($creditEntry->account->code)->toBe('1300')
        ->and($debitEntry->debit)->toEqual(1000.00)
        ->and($creditEntry->credit)->toEqual(1000.00);
});
