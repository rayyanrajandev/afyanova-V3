<?php

use App\Domains\Billing\Actions\GenerateInvoiceAction;
use App\Domains\Billing\Actions\IssueRefundAction;
use App\Domains\Billing\Actions\OpenCashierShiftAction;
use App\Domains\Billing\Actions\RecordPaymentAction;
use App\Domains\Billing\Exceptions\LedgerImbalanceException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Identity\Actions\AssignUserRoleAction;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Patient\Actions\RegisterPatientAction;

test('invoice can be generated from an encounter with line items', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Amani',
        'last_name' => 'Abeid',
        'gender' => 'Male',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    $generateAction = app(GenerateInvoiceAction::class);
    $invoice = $generateAction->execute($encounter);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->status)->toBe('Open')
        ->and((float) $invoice->total_amount)->toBe(20000.00)
        ->and($invoice->lineItems)->toHaveCount(1);
});

test('recording payment writes strictly balanced double-entry ledger journals', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Halima',
        'last_name' => 'Mdee',
        'gender' => 'Female',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    $invoice = app(GenerateInvoiceAction::class)->execute($encounter);

    // 1. Pay partial amount (10,000 TZS)
    $paymentAction = app(RecordPaymentAction::class);
    $tx1 = $paymentAction->execute($invoice, 10000.00, 'Cash');

    $invoice->refresh();
    expect($invoice->status)->toBe('Partially Paid')
        ->and((float) $invoice->paid_amount)->toBe(10000.00);

    // Check balanced entries: Total Debits == Total Credits
    $debits1 = (float) $tx1->entries()->sum('debit');
    $credits1 = (float) $tx1->entries()->sum('credit');
    expect($debits1)->toBe(10000.00)
        ->and($credits1)->toBe(10000.00);

    // 2. Pay remaining balance (10,000 TZS)
    $tx2 = $paymentAction->execute($invoice, 10000.00, 'M-Pesa');
    $invoice->refresh();
    expect($invoice->status)->toBe('Paid')
        ->and((float) $invoice->paid_amount)->toBe(20000.00);

    // 3. Overpayment must throw LedgerImbalanceException
    expect(fn () => $paymentAction->execute($invoice, 5000.00, 'Cash'))
        ->toThrow(LedgerImbalanceException::class);
});

test('refund creates balanced reversing ledger transaction', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Godbless',
        'last_name' => 'Lema',
        'gender' => 'Male',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    $invoice = app(GenerateInvoiceAction::class)->execute($encounter);
    app(RecordPaymentAction::class)->execute($invoice, 20000.00, 'Cash');

    $invoice->refresh();
    expect($invoice->status)->toBe('Paid');

    // Issue full refund
    $refundAction = app(IssueRefundAction::class);
    $refundTx = $refundAction->execute($invoice, 20000.00, 'Billing error overcharge');

    $invoice->refresh();
    expect($invoice->status)->toBe('Voided')
        ->and((float) $invoice->paid_amount)->toBe(0.00);

    // Check balanced reversal entries
    $debits = (float) $refundTx->entries()->sum('debit');
    $credits = (float) $refundTx->entries()->sum('credit');
    expect($debits)->toBe(20000.00)
        ->and($credits)->toBe(20000.00);
});

test('cashier cannot collect payment over HTTP without an active open shift', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];

    // Create Cashier Role & grant permissions
    $cashierRole = Role::create(['tenant_id' => $env['tenant']->id, 'slug' => 'cashier', 'name' => 'Cashier']);
    $billingPayPerm = Permission::firstOrCreate(['slug' => 'billing.payment.collect'], ['name' => 'Collect Payment', 'domain' => 'Billing']);
    $billingViewPerm = Permission::firstOrCreate(['slug' => 'billing.invoice.view'], ['name' => 'View Invoices', 'domain' => 'Billing']);
    $shiftOpenPerm = Permission::firstOrCreate(['slug' => 'billing.shift.open'], ['name' => 'Open Shift', 'domain' => 'Billing']);
    $cashierRole->permissions()->syncWithoutDetaching([$billingPayPerm->id, $billingViewPerm->id, $shiftOpenPerm->id]);
    app(AssignUserRoleAction::class)->execute($user->id, $cashierRole->id);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Fatuma',
        'last_name' => 'Kondo',
        'gender' => 'Female',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    $invoice = app(GenerateInvoiceAction::class)->execute($encounter);

    // 1. Attempt payment without opening shift -> Expect validation error
    $this->actingAs($user)
        ->post(route('billing.pay', $invoice->id), [
            'amount' => 20000.00,
            'payment_method' => 'Cash',
        ])
        ->assertSessionHasErrors('billing');

    $invoice->refresh();
    expect($invoice->status)->toBe('Open')
        ->and((float) $invoice->paid_amount)->toBe(0.00);

    // 2. Open Cashier Shift
    app(OpenCashierShiftAction::class)->execute(50000.00, 'Morning Desk 1');

    // 3. Re-attempt payment with open shift -> Succeeded
    $this->actingAs($user)
        ->post(route('billing.pay', $invoice->id), [
            'amount' => 20000.00,
            'payment_method' => 'Cash',
        ])
        ->assertSessionHasNoErrors();

    $invoice->refresh();
    expect($invoice->status)->toBe('Paid')
        ->and((float) $invoice->paid_amount)->toBe(20000.00);
});
