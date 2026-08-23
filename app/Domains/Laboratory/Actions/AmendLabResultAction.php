<?php

namespace App\Domains\Laboratory\Actions;

use App\Domains\Clinical\Models\LabOrderItem;
use Illuminate\Support\Facades\DB;

/**
 * The only sanctioned way to change an already-verified lab result. Marks
 * the original item deprecated and records a new item referencing it,
 * rather than overwriting a pathologist-verified finding in place.
 */
class AmendLabResultAction
{
    public function execute(LabOrderItem $originalItem, array $newResults, string $reason): LabOrderItem
    {
        return DB::transaction(function () use ($originalItem, $newResults, $reason) {
            LabOrderItem::withFinalizedMutation(function () use ($originalItem) {
                $originalItem->update(['is_deprecated' => true]);
            });

            return LabOrderItem::create([
                'lab_order_id' => $originalItem->lab_order_id,
                'lab_test_id' => $originalItem->lab_test_id,
                'price' => $originalItem->price,
                'status' => 'Completed',
                'specimen_barcode' => $originalItem->specimen_barcode,
                'results' => $newResults,
                'performed_by_id' => auth()->id(),

                'is_amendment' => true,
                'amended_result_item_id' => $originalItem->id,
                'amendment_reason' => $reason,
            ]);
        });
    }
}
