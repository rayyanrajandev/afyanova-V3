<?php

namespace App\Domains\Insurance\Actions;

use App\Domains\Insurance\Models\InsuranceClaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitClaimBatchAction
{
    public function execute(array $claimIds): string
    {
        return DB::transaction(function () use ($claimIds) {
            $batchNumber = 'BATCH-'.date('Y').'-'.strtoupper(Str::random(6));

            InsuranceClaim::whereIn('id', $claimIds)->update([
                'status' => 'Submitted',
                'batch_number' => $batchNumber,
                'submitted_at' => now(),
            ]);

            return $batchNumber;
        });
    }
}
