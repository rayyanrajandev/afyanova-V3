<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\StocktakeSession;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordBlindStocktakeCountAction
{
    /**
     * Records blind physical counts during a stocktake audit session.
     * System stores the counted quantities without revealing expected balance to counter.
     */
    public function execute(StocktakeSession $session, array $blindCounts, ?string $counterUserId = null): StocktakeSession
    {
        if ($session->status !== 'In_Progress' && $session->status !== 'Draft') {
            throw new InvalidArgumentException("Cannot record counts on a stocktake session with status '{$session->status}'.");
        }

        return DB::transaction(function () use ($session, $blindCounts, $counterUserId) {
            $session->update([
                'status' => 'Counts_Completed',
                'counted_by' => $counterUserId ?? auth()->id(),
                'counted_at' => now(),
            ]);

            // Delegate to reconciliation action if counts are submitted
            if (! empty($blindCounts)) {
                app(ReconcileStocktakeSessionAction::class)->execute(
                    $session->id,
                    $blindCounts,
                    $counterUserId ?? auth()->id()
                );
            }

            return $session->fresh(['location', 'items']);
        });
    }
}
