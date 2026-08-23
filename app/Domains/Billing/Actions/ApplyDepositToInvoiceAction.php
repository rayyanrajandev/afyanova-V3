<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\PatientDeposit;
use App\Domains\Billing\Models\PatientDepositAllocation;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplyDepositToInvoiceAction
{
    public function __construct(
        protected RecordPaymentAction $recordPaymentAction
    ) {}

    /**
     * Applies / draws down an unallocated patient deposit balance against an unpaid invoice.
     *
     * @param  float|null  $amountToApply  If null, applies min(deposit balance, invoice remaining)
     */
    public function execute(PatientDeposit $deposit, Invoice $invoice, ?float $amountToApply = null): PatientDepositAllocation
    {
        return DB::transaction(function () use ($deposit, $invoice, $amountToApply) {
            $lockedDeposit = PatientDeposit::where('id', $deposit->id)->lockForUpdate()->firstOrFail();
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedDeposit->patient_id !== $lockedInvoice->patient_id) {
                throw new InvalidArgumentException('Cannot apply deposit: Deposit patient does not match invoice patient.');
            }

            if ($lockedDeposit->status !== 'Active' || $lockedDeposit->balance_remaining <= 0) {
                throw new InvalidArgumentException("Deposit {$lockedDeposit->deposit_number} has no remaining balance.");
            }

            $invoiceRemaining = round(floatval($lockedInvoice->total_amount) - floatval($lockedInvoice->paid_amount), 2);
            if ($invoiceRemaining <= 0) {
                throw new InvalidArgumentException("Invoice {$lockedInvoice->invoice_number} is already fully paid.");
            }

            $drawdownAmount = $amountToApply !== null ? round(floatval($amountToApply), 2) : min($lockedDeposit->balance_remaining, $invoiceRemaining);

            if ($drawdownAmount <= 0) {
                throw new InvalidArgumentException('Drawdown amount must be greater than zero.');
            }

            if ($drawdownAmount > $lockedDeposit->balance_remaining) {
                throw new InvalidArgumentException("Cannot draw down {$drawdownAmount}. Only {$lockedDeposit->balance_remaining} available on deposit.");
            }

            if ($drawdownAmount > $invoiceRemaining) {
                throw new InvalidArgumentException("Cannot draw down {$drawdownAmount}. Only {$invoiceRemaining} outstanding on invoice.");
            }

            $userId = auth()->id() ?? User::first()?->id;

            // 1. Record the Payment using 'Deposit Drawdown' tender method (handles double entry ledgering)
            $this->recordPaymentAction->execute(
                $lockedInvoice,
                $drawdownAmount,
                'Deposit Drawdown',
                "Drawdown from Deposit {$lockedDeposit->deposit_number}"
            );

            // 2. Deduct from Deposit balance
            $newDepositBalance = round(floatval($lockedDeposit->balance_remaining) - $drawdownAmount, 2);
            $lockedDeposit->update([
                'balance_remaining' => $newDepositBalance,
                'status' => $newDepositBalance <= 0.001 ? 'Depleted' : 'Active',
            ]);

            // 3. Create allocation record
            $allocation = PatientDepositAllocation::create([
                'tenant_id' => $lockedDeposit->tenant_id,
                'deposit_id' => $lockedDeposit->id,
                'invoice_id' => $lockedInvoice->id,
                'allocated_amount' => $drawdownAmount,
                'allocated_by' => $userId,
                'allocated_at' => now(),
            ]);

            return $allocation->fresh(['deposit', 'invoice']);
        });
    }
}
