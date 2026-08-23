<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Exceptions\LedgerImbalanceException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\LedgerAccount;
use App\Domains\Billing\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;

class IssueRefundAction
{
    public function execute(Invoice $invoice, float $amount, string $reason): LedgerTransaction
    {
        return DB::transaction(function () use ($invoice, $amount, $reason) {

            // 1. Update Invoice
            $newPaid = $invoice->paid_amount - $amount;
            $invoice->update([
                'paid_amount' => $newPaid,
                'status' => ($newPaid == 0) ? 'Voided' : 'Partially Paid',
            ]);

            // 2. Reversing Ledger Transaction
            $cashAccount = LedgerAccount::where('code', '1000')->first();
            $revenueAccount = LedgerAccount::where('code', '4000')->first();

            $transaction = LedgerTransaction::create([
                'facility_id' => $invoice->facility_id,
                'user_id' => auth()->id(),
                'reference_type' => 'Refund',
                'reference_id' => $invoice->id,
                'description' => "Refund for {$invoice->invoice_number}. Reason: {$reason}",
            ]);

            // Debit Revenue (Revenue decreases)
            $transaction->entries()->create([
                'account_id' => $revenueAccount->id,
                'debit' => $amount,
                'credit' => 0.00,
            ]);

            // Credit Cash (Asset decreases)
            $transaction->entries()->create([
                'account_id' => $cashAccount->id,
                'debit' => 0.00,
                'credit' => $amount,
            ]);

            // 3. Check Balance
            $totalDebits = $transaction->entries()->sum('debit');
            $totalCredits = $transaction->entries()->sum('credit');

            if (abs($totalDebits - $totalCredits) > 0.001) {
                throw LedgerImbalanceException::unbalanced($totalDebits, $totalCredits);
            }

            return $transaction;
        });
    }
}
