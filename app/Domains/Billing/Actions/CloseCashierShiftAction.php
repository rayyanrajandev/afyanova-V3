<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\CashierShift;
use App\Domains\Billing\Models\LedgerAccount;
use App\Domains\Billing\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseCashierShiftAction
{
    public function execute(CashierShift $shift, float $closingCashCounted, ?string $notes = null): CashierShift
    {
        if ($shift->status !== 'Open') {
            throw ValidationException::withMessages(['shift' => 'Only open shifts can be closed.']);
        }

        return DB::transaction(function () use ($shift, $closingCashCounted, $notes) {
            // Find Cash Account (code 1000)
            $cashAccount = LedgerAccount::where('code', '1000')
                ->where('tenant_id', $shift->tenant_id)
                ->first();

            $cashCollected = 0.00;
            $cashRefunded = 0.00;

            if ($cashAccount) {
                // Sum debits to cash (inflows) during the shift duration by this user
                $cashCollected = (float) LedgerEntry::where('account_id', $cashAccount->id)
                    ->whereHas('transaction', function ($q) use ($shift) {
                        $q->where('user_id', $shift->user_id)
                            ->where('created_at', '>=', $shift->opened_at);
                    })
                    ->sum('debit');

                // Sum credits to cash (refunds/outflows) during the shift duration by this user
                $cashRefunded = (float) LedgerEntry::where('account_id', $cashAccount->id)
                    ->whereHas('transaction', function ($q) use ($shift) {
                        $q->where('user_id', $shift->user_id)
                            ->where('created_at', '>=', $shift->opened_at);
                    })
                    ->sum('credit');
            }

            $expectedCashTotal = (float) $shift->opening_float + $cashCollected - $cashRefunded;
            $discrepancy = (float) $closingCashCounted - $expectedCashTotal;

            $varianceStatus = 'Balanced';
            if ($discrepancy > 0.001) {
                $varianceStatus = 'Overage';
            } elseif ($discrepancy < -0.001) {
                $varianceStatus = 'Shortage';
            }

            $shift->update([
                'status' => 'Closed',
                'closed_at' => now(),
                'closing_cash_counted' => $closingCashCounted,
                'expected_cash_total' => $expectedCashTotal,
                'discrepancy' => $discrepancy,
                'variance_status' => $varianceStatus,
                'notes' => $notes ?? $shift->notes,
            ]);

            return $shift;
        });
    }
}
