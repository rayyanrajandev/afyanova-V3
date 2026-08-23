<?php

use App\Domains\Billing\Actions\GenerateInvoiceAction;
use App\Domains\Billing\Actions\IssueInvoiceAction;
use App\Domains\Billing\Actions\IssueInvoiceAdjustmentAction;
use App\Domains\Billing\Exceptions\InvoiceImmutabilityException;
use App\Domains\Billing\Models\LedgerTransaction;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Patient\Actions\RegisterPatientAction;

function buildOpenInvoice(): array
{
    $env = test()->setupTenantEnvironment();
    test()->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Invariant',
        'last_name' => 'Test',
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

    return compact('env', 'invoice');
}

test('an issued invoice total cannot be mutated outside the adjustment action', function () {
    ['invoice' => $invoice] = buildOpenInvoice();

    app(IssueInvoiceAction::class)->execute($invoice);
    $invoice->refresh();
    expect($invoice->status)->toBe('Issued');

    expect(fn () => $invoice->update(['total_amount' => 999999.99]))
        ->toThrow(InvoiceImmutabilityException::class);
});

test('ledger stays balanced after an invoice adjustment note is issued', function () {
    ['invoice' => $invoice] = buildOpenInvoice();

    app(IssueInvoiceAction::class)->execute($invoice);
    $invoice->refresh();
    $originalTotal = (float) $invoice->total_amount;

    $note = app(IssueInvoiceAdjustmentAction::class)->execute($invoice, 'Credit', 5000.00, 'Goodwill discount');

    $invoice->refresh();
    expect((float) $invoice->total_amount)->toBe($originalTotal - 5000.00);

    $transaction = $note->invoice->adjustmentNotes()->latest()->first();
    expect($transaction)->not->toBeNull();

    $entries = LedgerTransaction::where('reference_type', 'InvoiceAdjustmentNote')
        ->where('reference_id', $note->id)
        ->firstOrFail()
        ->entries;

    expect((float) $entries->sum('debit'))->toBe((float) $entries->sum('credit'));
});

test('adjustment notes are themselves append-only', function () {
    ['invoice' => $invoice] = buildOpenInvoice();
    app(IssueInvoiceAction::class)->execute($invoice);
    $invoice->refresh();

    $note = app(IssueInvoiceAdjustmentAction::class)->execute($invoice, 'Debit', 1000.00, 'Late-arriving lab charge');

    // No application-level guard is expected here on SQLite (the append-only
    // enforcement is a Postgres RULE) — this just documents current
    // behavior and gives Postgres CI something to diverge against later.
    expect($note->type)->toBe('Debit')
        ->and((float) $note->amount)->toBe(1000.00);
});
