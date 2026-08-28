<?php

namespace App\Domains\Billing\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Billing\Exceptions\LedgerImbalanceException;
use App\Domains\Billing\Models\CashierShift;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\LedgerAccount;
use App\Domains\Billing\Models\LedgerTransaction;
use App\Domains\Billing\Models\Payment;
use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordPaymentAction
{
    public function execute(Invoice $invoice, float $amount, string $paymentMethod, ?string $referenceNumber = null): LedgerTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($invoice, $amount, $paymentMethod, $referenceNumber) {
            // Acquire exclusive row lock on Invoice to prevent concurrent payment race conditions
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            $remaining = round(floatval($lockedInvoice->total_amount) - floatval($lockedInvoice->paid_amount), 2);
            if ($amount > $remaining) {
                throw LedgerImbalanceException::overPayment($amount, $remaining);
            }

            if ($lockedInvoice->status === 'Paid') {
                throw new InvalidArgumentException("Invoice {$lockedInvoice->invoice_number} is already fully Paid.");
            }

            $userId = auth()->id() ?? User::first()?->id;
            $tenantId = app(TenantContext::class)->getTenantId() ?? $lockedInvoice->tenant_id;
            $facilityId = $lockedInvoice->facility_id ?? app(FacilityContext::class)->getFacilityId();

            // Find active cashier shift for this user if one exists
            $activeShift = CashierShift::where('user_id', $userId)
                ->where('status', 'Open')
                ->latest('opened_at')
                ->first();

            // 1. Update the Invoice
            $newPaid = round(floatval($lockedInvoice->paid_amount) + $amount, 2);
            $isFullyPaid = abs($newPaid - floatval($lockedInvoice->total_amount)) < 0.001;

            $lockedInvoice->update([
                'paid_amount' => $newPaid,
                'status' => $isFullyPaid ? 'Paid' : 'Partially Paid',
            ]);

            // If consultation fee is fully settled, advance the patient queue ticket to Doctor consultation
            if ($isFullyPaid && $lockedInvoice->encounter_id) {
                QueueTicket::where('encounter_id', $lockedInvoice->encounter_id)
                    ->where('status', QueueTicketStatus::Waiting)
                    ->where('current_service_point', 'Cashier')
                    ->update([
                        'current_service_point' => 'Doctor',
                    ]);
            }

            // 2. Create Payment Receipt Record
            $receiptNumber = 'RCP-'.date('Y').'-'.strtoupper(Str::random(6));
            $payment = Payment::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'invoice_id' => $lockedInvoice->id,
                'cashier_shift_id' => $activeShift?->id,
                'user_id' => $userId,
                'receipt_number' => $receiptNumber,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'transaction_reference' => $referenceNumber,
                'status' => 'Completed',
                'notes' => "Settlement of {$lockedInvoice->invoice_number} via {$paymentMethod}",
            ]);

            // 3. Double-Entry General Ledger Logic
            $accountCode = match ($paymentMethod) {
                'Lipa Namba', 'M-Pesa', 'Tigo Pesa', 'Airtel Money', 'Halopesa' => '1020',
                'Card', 'Bank POS' => '1030',
                'Deposit Drawdown' => '2100', // Patient Prepayment / Deposit Liability Account
                'NHIF', 'Insurance' => '1200',
                default => '1000',
            };
            $accountName = match ($paymentMethod) {
                'Lipa Namba', 'M-Pesa', 'Tigo Pesa', 'Airtel Money', 'Halopesa' => 'Mobile Money / Lipa Namba',
                'Card', 'Bank POS' => 'Bank POS Terminal',
                'Deposit Drawdown' => 'Patient Advance Deposits (Liability)',
                'NHIF', 'Insurance' => 'Insurance Accounts Receivable',
                default => 'Cash in Hand',
            };

            $cashOrDepositAccount = LedgerAccount::firstOrCreate(
                ['code' => $accountCode, 'tenant_id' => $tenantId],
                ['name' => $accountName, 'type' => $accountCode === '2100' ? 'Liability' : 'Asset']
            );
            $revenueAccount = LedgerAccount::firstOrCreate(
                ['code' => '4000', 'tenant_id' => $tenantId],
                ['name' => 'Service Revenue', 'type' => 'Revenue']
            );

            $refText = $referenceNumber ? " (Ref: {$referenceNumber})" : '';
            $transaction = LedgerTransaction::create([
                'facility_id' => $facilityId,
                'user_id' => $userId,
                'reference_type' => 'Invoice',
                'reference_id' => $lockedInvoice->id,
                'description' => "Payment for {$lockedInvoice->invoice_number} via {$paymentMethod} [Receipt {$receiptNumber}]{$refText}",
            ]);

            // If Deposit Drawdown: Debit Deposit Liability (Liability decreases)
            // If Cash/Bank/M-Pesa: Debit Cash Asset (Asset increases)
            $transaction->entries()->create([
                'account_id' => $cashOrDepositAccount->id,
                'debit' => $amount,
                'credit' => 0.00,
            ]);

            // Credit Revenue (Revenue increases)
            $transaction->entries()->create([
                'account_id' => $revenueAccount->id,
                'debit' => 0.00,
                'credit' => $amount,
            ]);

            // 4. Mathematical Ledger Imbalance Invariant Check
            $totalDebits = $transaction->entries()->sum('debit');
            $totalCredits = $transaction->entries()->sum('credit');

            if (abs($totalDebits - $totalCredits) > 0.001) {
                throw LedgerImbalanceException::unbalanced($totalDebits, $totalCredits);
            }

            return $transaction;
        });
    }
}
