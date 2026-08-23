<?php

namespace App\Domains\Laboratory\Actions;

use App\Domains\Clinical\Models\LabOrderItem;
use Illuminate\Support\Facades\DB;

class VerifyLabResultsAction
{
    public function execute(LabOrderItem $item, ?string $pathologistNotes = null): LabOrderItem
    {
        return DB::transaction(function () use ($item, $pathologistNotes) {
            $item->load('labOrder.items');

            $item->update([
                'status' => 'Completed',
                'verified_by_id' => auth()->id(),
                'technician_remarks' => $pathologistNotes ? ($item->technician_remarks ? $item->technician_remarks.' | Pathologist Sign-off: '.$pathologistNotes : 'Pathologist Sign-off: '.$pathologistNotes) : $item->technician_remarks,
            ]);

            $order = $item->labOrder;
            if ($order) {
                $allCompleted = $order->items()->where('status', '!=', 'Completed')->count() === 0;
                if ($allCompleted) {
                    $order->update([
                        'status' => 'Completed',
                        'completed_at' => now(),
                    ]);
                }
            }

            return $item->fresh(['labTest', 'labOrder.patient', 'performedBy', 'verifiedBy']);
        });
    }
}
