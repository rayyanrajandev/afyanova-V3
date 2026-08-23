<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Exceptions\LedgerImbalanceException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceAdjustmentNote;
use App\Domains\Billing\Models\LedgerAccount;
use App\Domains\Billing\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;

/**
 * The only sanctioned way to change an already-issued invoice's total.
 * Records an immutable CreditNote/DebitNote and posts the balancing
 * Accounts-Receivable/Revenue ledger entries in the same transaction —
 * the invoice's total_amount is corrected, never overwritten in place.
 */
class IssueInvoiceAdjustmentAction
{
    public function execute(Invoice $invoice, string $type, float $amount, string $reason): InvoiceAdjustmentNote
    {
        return DB::transaction(function () use ($invoice, $type, $amount, $reason) {
            $note = InvoiceAdjustmentNote::create([
                'tenant_id' => $invoice->tenant_id,
                'facility_id' => $invoice->facility_id,
                'invoice_id' => $invoice->id,
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'created_by' => auth()->id(),
            ]);

            $receivableAccount = LedgerAccount::firstOrCreate(
                ['code' => '1100', 'tenant_id' => $invoice->tenant_id],
                ['name' => 'Accounts Receivable - Patient', 'type' => 'Asset']
            );
            $revenueAccount = LedgerAccount::firstOrCreate(
                ['code' => '4000', 'tenant_id' => $invoice->tenant_id],
                ['name' => 'Service Revenue', 'type' => 'Revenue']
            );

            $transaction = LedgerTransaction::create([
                'facility_id' => $invoice->facility_id,
                'user_id' => auth()->id(),
                'reference_type' => 'InvoiceAdjustmentNote',
                'reference_id' => $note->id,
                'description' => "{$type} note for {$invoice->invoice_number}. Reason: {$reason}",
            ]);

            if ($type === 'Credit') {
                // Amount owed decreases: revenue down, receivable down.
                $transaction->entries()->create(['account_id' => $revenueAccount->id, 'debit' => $amount, 'credit' => 0.00]);
                $transaction->entries()->create(['account_id' => $receivableAccount->id, 'debit' => 0.00, 'credit' => $amount]);
            } else {
                // Amount owed increases: receivable up, revenue up.
                $transaction->entries()->create(['account_id' => $receivableAccount->id, 'debit' => $amount, 'credit' => 0.00]);
                $transaction->entries()->create(['account_id' => $revenueAccount->id, 'debit' => 0.00, 'credit' => $amount]);
            }

            $totalDebits = $transaction->entries()->sum('debit');
            $totalCredits = $transaction->entries()->sum('credit');
            if (abs($totalDebits - $totalCredits) > 0.001) {
                throw LedgerImbalanceException::unbalanced($totalDebits, $totalCredits);
            }

            Invoice::withLockedTotalMutation(function () use ($invoice, $type, $amount) {
                $delta = $type === 'Credit' ? -$amount : $amount;
                $invoice->update(['total_amount' => $invoice->total_amount + $delta]);
            });

            return $note;
        });
    }
}
