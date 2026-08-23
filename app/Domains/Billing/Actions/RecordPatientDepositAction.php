<?php

namespace App\Domains\Billing\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Billing\Exceptions\LedgerImbalanceException;
use App\Domains\Billing\Models\CashierShift;
use App\Domains\Billing\Models\LedgerAccount;
use App\Domains\Billing\Models\LedgerTransaction;
use App\Domains\Billing\Models\PatientDeposit;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordPatientDepositAction
{
    /**
     * Records advance unallocated patient prepayment deposit.
     * Generates a double-entry ledger entry:
     * Debit: Cash / Bank Asset (increases)
     * Credit: Patient Prepayment / Deposit Liability (increases)
     */
    public function execute(Patient $patient, float $amount, string $paymentMethod, ?string $referenceNumber = null, ?string $notes = null): PatientDeposit
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than zero.');
        }

        if ($patient->isDeceased()) {
            throw new InvalidArgumentException('Cannot accept deposit for a deceased patient.');
        }

        if ($patient->isMerged()) {
            throw new InvalidArgumentException('Cannot accept deposit on a merged patient record.');
        }

        return DB::transaction(function () use ($patient, $amount, $paymentMethod, $referenceNumber, $notes) {
            $userId = auth()->id() ?? User::first()?->id;
            $tenantId = app(TenantContext::class)->getTenantId() ?? $patient->tenant_id;
            $facilityId = app(FacilityContext::class)->getFacilityId()
                ?? auth()->user()?->facility_id
                ?? Facility::where('tenant_id', $tenantId)->first()?->id;

            $activeShift = CashierShift::where('user_id', $userId)
                ->where('status', 'Open')
                ->latest('opened_at')
                ->first();

            $depositNumber = 'DEP-'.date('Y').'-'.strtoupper(Str::random(6));

            // 1. Create Patient Deposit record
            $deposit = PatientDeposit::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'patient_id' => $patient->id,
                'user_id' => $userId,
                'cashier_shift_id' => $activeShift?->id,
                'deposit_number' => $depositNumber,
                'amount' => $amount,
                'balance_remaining' => $amount,
                'payment_method' => $paymentMethod,
                'transaction_reference' => $referenceNumber,
                'status' => 'Active',
                'notes' => $notes,
            ]);

            // 2. Double-Entry General Ledger Logic
            $assetCode = match ($paymentMethod) {
                'Lipa Namba', 'M-Pesa', 'Tigo Pesa', 'Airtel Money', 'Halopesa' => '1020',
                'Card', 'Bank POS' => '1030',
                default => '1000',
            };
            $assetName = match ($paymentMethod) {
                'Lipa Namba', 'M-Pesa', 'Tigo Pesa', 'Airtel Money', 'Halopesa' => 'Mobile Money / Lipa Namba',
                'Card', 'Bank POS' => 'Bank POS Terminal',
                default => 'Cash in Hand',
            };

            $cashAccount = LedgerAccount::firstOrCreate(
                ['code' => $assetCode, 'tenant_id' => $tenantId],
                ['name' => $assetName, 'type' => 'Asset']
            );

            // Prepayment Liability Account Code 2100
            $depositLiabilityAccount = LedgerAccount::firstOrCreate(
                ['code' => '2100', 'tenant_id' => $tenantId],
                ['name' => 'Patient Advance Deposits (Liability)', 'type' => 'Liability']
            );

            $refText = $referenceNumber ? " (Ref: {$referenceNumber})" : '';
            $transaction = LedgerTransaction::create([
                'facility_id' => $facilityId,
                'user_id' => $userId,
                'reference_type' => 'PatientDeposit',
                'reference_id' => $deposit->id,
                'description' => "Patient Deposit {$depositNumber} received from {$patient->first_name} {$patient->last_name} via {$paymentMethod}{$refText}",
            ]);

            // Debit Cash (Asset increases)
            $transaction->entries()->create([
                'account_id' => $cashAccount->id,
                'debit' => $amount,
                'credit' => 0.00,
            ]);

            // Credit Deposit Liability (Liability increases)
            $transaction->entries()->create([
                'account_id' => $depositLiabilityAccount->id,
                'debit' => 0.00,
                'credit' => $amount,
            ]);

            // 3. Imbalance Invariant Check
            $totalDebits = $transaction->entries()->sum('debit');
            $totalCredits = $transaction->entries()->sum('credit');

            if (abs($totalDebits - $totalCredits) > 0.001) {
                throw LedgerImbalanceException::unbalanced($totalDebits, $totalCredits);
            }

            return $deposit;
        });
    }
}
