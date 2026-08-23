<?php

namespace App\Domains\Insurance\Actions;

use App\Domains\Insurance\Models\InsuranceClaim;
use Illuminate\Support\Facades\DB;

class AdjudicateClaimAction
{
    public function execute(InsuranceClaim $claim, string $status, ?float $approvedAmount = null, ?string $notes = null): InsuranceClaim
    {
        return DB::transaction(function () use ($claim, $status, $approvedAmount, $notes) {
            $finalApproved = $approvedAmount !== null ? $approvedAmount : ($status === 'Approved' ? $claim->total_claimed_amount - $claim->co_pay_amount : 0.00);

            $claim->update([
                'status' => $status,
                'approved_amount' => $finalApproved,
                'rejection_reason' => $notes ?: $claim->rejection_reason,
                'adjudicated_at' => now(),
            ]);

            // Update item statuses accordingly
            if ($status === 'Approved') {
                $claim->items()->update([
                    'status' => 'Approved',
                    'approved_amount' => DB::raw('claimed_amount'),
                ]);
            } elseif ($status === 'Rejected') {
                $claim->items()->update([
                    'status' => 'Disallowed',
                    'approved_amount' => 0.00,
                    'disallowance_reason' => $notes ?: 'Insurer audit deduction',
                ]);
            }

            return $claim->fresh(['patient', 'policy.provider', 'policy.scheme', 'encounter', 'items']);
        });
    }
}
