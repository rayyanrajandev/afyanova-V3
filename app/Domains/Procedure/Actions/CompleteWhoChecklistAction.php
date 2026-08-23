<?php

namespace App\Domains\Procedure\Actions;

use App\Domains\Procedure\Models\WhoSurgicalChecklist;
use Illuminate\Support\Facades\DB;

class CompleteWhoChecklistAction
{
    public function execute(WhoSurgicalChecklist $checklist, string $stage, array $data = []): WhoSurgicalChecklist
    {
        return DB::transaction(function () use ($checklist, $stage, $data) {
            $userId = auth()->id();

            if ($stage === 'sign_in') {
                $checklist->update([
                    'sign_in_completed_at' => now(),
                    'sign_in_verified_by' => $userId,
                ]);
            } elseif ($stage === 'time_out') {
                $checklist->update([
                    'time_out_completed_at' => now(),
                    'time_out_verified_by' => $userId,
                ]);
            } elseif ($stage === 'sign_out') {
                $checklist->update([
                    'sign_out_completed_at' => now(),
                    'sign_out_verified_by' => $userId,
                    'sponge_and_needle_count_correct' => $data['sponge_and_needle_count_correct'] ?? true,
                    'specimens_labeled_correctly' => $data['specimens_labeled_correctly'] ?? true,
                ]);
            }

            return $checklist->fresh(['booking.leadSurgeon', 'signInVerifier', 'timeOutVerifier', 'signOutVerifier']);
        });
    }
}
