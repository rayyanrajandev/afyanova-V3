<?php

namespace App\Domains\Insurance\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Billing\Exceptions\LedgerImbalanceException;
use App\Domains\Billing\Models\LedgerAccount;
use App\Domains\Billing\Models\LedgerTransaction;
use App\Domains\Identity\Models\User;
use App\Domains\Insurance\Models\ClaimRemittance;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Insurance\Models\InsuranceProvider;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProcessRemittanceAdviceAction
{
    /**
     * Adjudicates a batch remittance advice from an insurance payer.
     * Posts balanced General Ledger entries for settlement and disallowances.
     *
     * @param  string  $paymentReference  Bank transfer / EFT reference
     * @param  array<int, array{claim_id: string, settled_amount: float, disallowed_amount: float, reason_code?: string, remarks?: string}>  $claimLines
     */
    public function execute(
        InsuranceProvider $provider,
        string $paymentReference,
        string $remittanceDate,
        array $claimLines,
        ?string $notes = null
    ): ClaimRemittance {
        if (empty($claimLines)) {
            throw new InvalidArgumentException('Remittance advice batch must contain at least one claim line.');
        }

        return DB::transaction(function () use ($provider, $paymentReference, $remittanceDate, $claimLines, $notes) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $provider->tenant_id;
            $facilityId = app(FacilityContext::class)->getFacilityId()
                ?? auth()->user()?->facility_id
                ?? Facility::where('tenant_id', $tenantId)->first()?->id;
            $userId = auth()->id() ?? User::first()?->id;

            $totalClaimed = 0.00;
            $totalSettled = 0.00;
            $totalDisallowed = 0.00;

            $remittanceNumber = 'REM-'.date('Y').'-'.strtoupper(Str::random(6));

            $remittance = ClaimRemittance::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'insurance_provider_id' => $provider->id,
                'processed_by' => $userId,
                'remittance_number' => $remittanceNumber,
                'payment_reference' => $paymentReference,
                'total_claimed_amount' => 0.00,
                'total_settled_amount' => 0.00,
                'total_disallowed_amount' => 0.00,
                'status' => 'Processed',
                'remittance_date' => $remittanceDate,
                'notes' => $notes,
            ]);

            foreach ($claimLines as $line) {
                $claim = InsuranceClaim::where('id', $line['claim_id'])->lockForUpdate()->firstOrFail();

                $claimedAmt = floatval($claim->approved_amount > 0 ? $claim->approved_amount : $claim->total_claimed_amount);
                $settledAmt = round(floatval($line['settled_amount']), 2);
                $disallowedAmt = round(floatval($line['disallowed_amount'] ?? ($claimedAmt - $settledAmt)), 2);

                $totalClaimed += $claimedAmt;
                $totalSettled += $settledAmt;
                $totalDisallowed += $disallowedAmt;

                $adjudicationStatus = 'PaidInFull';
                if ($settledAmt === 0.00) {
                    $adjudicationStatus = 'Rejected';
                } elseif ($disallowedAmt > 0.00) {
                    $adjudicationStatus = 'PartiallySettled';
                }

                $remittance->items()->create([
                    'tenant_id' => $tenantId,
                    'insurance_claim_id' => $claim->id,
                    'claimed_amount' => $claimedAmt,
                    'settled_amount' => $settledAmt,
                    'disallowed_amount' => $disallowedAmt,
                    'disallowance_reason_code' => $line['reason_code'] ?? null,
                    'disallowance_remarks' => $line['remarks'] ?? null,
                    'adjudication_status' => $adjudicationStatus,
                ]);

                // Update claim status
                $claimStatus = match ($adjudicationStatus) {
                    'PaidInFull' => 'Paid',
                    'PartiallySettled' => 'Partially Paid',
                    default => 'Rejected',
                };
                $claim->update([
                    'status' => $claimStatus,
                    'adjudicated_amount' => $settledAmt,
                ]);
            }

            $remittance->update([
                'total_claimed_amount' => $totalClaimed,
                'total_settled_amount' => $totalSettled,
                'total_disallowed_amount' => $totalDisallowed,
            ]);

            // Double-Entry General Ledger Logic:
            // 1. Debit Bank Asset (Account 1030) for Settled Amount
            // 2. Debit Insurance Disallowance Expense (Account 5100) for Disallowed Amount (if any)
            // 3. Credit Insurance Accounts Receivable (Account 1200) for Total Claimed Amount
            $bankAccount = LedgerAccount::firstOrCreate(
                ['code' => '1030', 'tenant_id' => $tenantId],
                ['name' => 'Bank Account / EFT Settlement', 'type' => 'Asset']
            );

            $receivableAccount = LedgerAccount::firstOrCreate(
                ['code' => '1200', 'tenant_id' => $tenantId],
                ['name' => 'Insurance Accounts Receivable', 'type' => 'Asset']
            );

            $disallowanceAccount = LedgerAccount::firstOrCreate(
                ['code' => '5100', 'tenant_id' => $tenantId],
                ['name' => 'Insurance Claim Disallowances / Contractual Write-offs', 'type' => 'Expense']
            );

            $transaction = LedgerTransaction::create([
                'facility_id' => $facilityId,
                'user_id' => $userId,
                'reference_type' => 'ClaimRemittance',
                'reference_id' => $remittance->id,
                'description' => "Remittance {$remittanceNumber} from {$provider->name} (EFT: {$paymentReference})",
            ]);

            // Debit Bank for Settled Funds
            if ($totalSettled > 0) {
                $transaction->entries()->create([
                    'account_id' => $bankAccount->id,
                    'debit' => $totalSettled,
                    'credit' => 0.00,
                ]);
            }

            // Debit Disallowance Expense for Denied Portions
            if ($totalDisallowed > 0) {
                $transaction->entries()->create([
                    'account_id' => $disallowanceAccount->id,
                    'debit' => $totalDisallowed,
                    'credit' => 0.00,
                ]);
            }

            // Credit Accounts Receivable
            $transaction->entries()->create([
                'account_id' => $receivableAccount->id,
                'debit' => 0.00,
                'credit' => $totalClaimed,
            ]);

            // Invariant Check: Sum(Debits) === Sum(Credits)
            $totalDebits = $transaction->entries()->sum('debit');
            $totalCredits = $transaction->entries()->sum('credit');

            if (abs($totalDebits - $totalCredits) > 0.001) {
                throw LedgerImbalanceException::unbalanced($totalDebits, $totalCredits);
            }

            return $remittance->fresh(['provider', 'processor', 'items.claim']);
        });
    }
}
