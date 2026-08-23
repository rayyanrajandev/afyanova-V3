<?php

namespace App\Domains\Laboratory\Actions;

use App\Domains\Clinical\Models\LabOrderItem;
use Illuminate\Support\Facades\DB;

class RecordLabResultsAction
{
    public function execute(LabOrderItem $item, array $results, ?string $technicianRemarks = null): LabOrderItem
    {
        return DB::transaction(function () use ($item, $results, $technicianRemarks) {
            $item->load('labTest', 'labOrder.items');
            $test = $item->labTest;
            $hasCritical = false;

            // Evaluate parameters for Panic / Critical / Abnormal boundaries
            if ($test && is_array($test->parameters)) {
                foreach ($test->parameters as $param) {
                    $paramName = $param['name'] ?? null;
                    if (! $paramName || ! isset($results[$paramName])) {
                        continue;
                    }

                    $val = $results[$paramName];

                    // Check numeric panic thresholds
                    if (is_numeric($val)) {
                        $numVal = floatval($val);
                        if (isset($param['panic_low']) && $param['panic_low'] !== null && $numVal <= floatval($param['panic_low'])) {
                            $hasCritical = true;
                        }
                        if (isset($param['panic_high']) && $param['panic_high'] !== null && $numVal >= floatval($param['panic_high'])) {
                            $hasCritical = true;
                        }
                    } else {
                        // Qualitative critical findings (e.g. "Positive", "Reactive", "Heavy Growth")
                        if (isset($param['critical_value']) && strcasecmp(trim($val), trim($param['critical_value'])) === 0) {
                            $hasCritical = true;
                        }
                    }
                }
            }

            $item->update([
                'results' => $results,
                'technician_remarks' => $technicianRemarks,
                'has_critical_value' => $hasCritical,
                'critical_value_alerted_at' => $hasCritical ? now() : null,
                'status' => 'Completed',
                'performed_by_id' => auth()->id() ?: $item->performed_by_id,
            ]);

            // Check if all items in the order are completed
            $order = $item->labOrder;
            if ($order) {
                $allCompleted = $order->items()->where('status', '!=', 'Completed')->count() === 0;

                if ($allCompleted) {
                    $order->update([
                        'status' => 'Completed',
                        'completed_at' => now(),
                    ]);
                } else {
                    $order->update([
                        'status' => 'In Progress',
                    ]);
                }
            }

            return $item->fresh(['labTest', 'labOrder.patient', 'performedBy', 'verifiedBy']);
        });
    }
}
