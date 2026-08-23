<?php

namespace App\Domains\Procedure\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\StockMovement;
use App\Domains\Procedure\Models\ProcedureExecution;
use App\Domains\Procedure\Models\ProcedureOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordProcedureExecutionAction
{
    public function execute(ProcedureOrder $order, array $data, array $consumables = []): ProcedureExecution
    {
        $patient = $order->patient;
        if ($patient?->isDeceased()) {
            throw new InvalidArgumentException("Cannot record procedure execution. Patient {$patient->first_name} {$patient->last_name} is recorded as Deceased.");
        }
        if ($patient?->isMerged()) {
            throw new InvalidArgumentException("Cannot record procedure execution. Patient record has been merged into {$patient->merged_into_patient_id}.");
        }

        // Surgical Safety Invariant: Operating Theatre procedures require completed WHO Time-Out
        $isSurgical = in_array($data['execution_setting'] ?? '', ['OperatingTheatre', 'MinorTheatre', 'MajorTheatre'])
            || $order->surgicalBooking !== null;

        if ($isSurgical) {
            $booking = $order->surgicalBooking;
            $checklist = $booking?->whoChecklist;

            if (! $checklist || empty($checklist->time_out_completed_at)) {
                throw new InvalidArgumentException(
                    "Surgical procedure execution blocked: WHO Surgical Safety Checklist 'Time-Out' has not been completed and verified."
                );
            }
        }

        return DB::transaction(function () use ($order, $data, $consumables) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $order->tenant_id;

            $execution = ProcedureExecution::create([
                'tenant_id' => $tenantId,
                'procedure_order_id' => $order->id,
                'performed_by_id' => $data['performed_by_id'] ?? auth()->id(),
                'assistant_id' => $data['assistant_id'] ?? null,
                'execution_setting' => $data['execution_setting'] ?? 'DressingRoom',
                'anesthesia_type' => $data['anesthesia_type'] ?? 'Local',
                'wound_condition' => $data['wound_condition'] ?? 'Clean',
                'findings_and_technique' => $data['findings_and_technique'],
                'post_procedure_instructions' => $data['post_procedure_instructions'] ?? null,
                'follow_up_date' => $data['follow_up_date'] ?? null,
                'started_at' => $data['started_at'] ?? now()->subMinutes(15),
                'completed_at' => now(),
            ]);

            // Consumable Materials Tracking & Inventory Stock Deduction
            foreach ($consumables as $item) {
                $batchId = $item['batch_id'] ?? null;
                $medicationId = $item['medication_id'] ?? null;
                $qty = floatval($item['quantity_used'] ?? 1.00);
                $unitPrice = floatval($item['unit_price'] ?? 0.00);

                $execution->consumables()->create([
                    'tenant_id' => $tenantId,
                    'item_name' => $item['item_name'],
                    'medication_id' => $medicationId,
                    'batch_id' => $batchId,
                    'quantity_used' => $qty,
                    'unit_price' => $unitPrice,
                    'is_billed_to_patient' => $item['is_billed_to_patient'] ?? true,
                ]);

                // Atomic Stock Movement Decrement if batch exists
                if ($batchId) {
                    $batch = InventoryBatch::find($batchId);
                    if ($batch && $batch->current_quantity >= $qty) {
                        $qtyBefore = $batch->current_quantity;
                        $batch->decrement('current_quantity', $qty);
                        $qtyAfter = $batch->current_quantity;

                        StockMovement::create([
                            'tenant_id' => $tenantId,
                            'facility_id' => $order->encounter?->facility_id ?? $batch->facility_id,
                            'medication_id' => $batch->medication_id,
                            'batch_id' => $batch->id,
                            'movement_type' => 'Dispensed',
                            'quantity_change' => -$qty,
                            'quantity_before' => $qtyBefore,
                            'quantity_after' => $qtyAfter,
                            'reference_type' => 'ProcedureExecution',
                            'reference_id' => $execution->id,
                            'notes' => "Dressing / Procedure consumable: {$item['item_name']} for {$order->patient?->first_name} {$order->patient?->last_name}",
                            'performed_by' => auth()->id() ?? $order->encounter?->provider_id,
                        ]);
                    }
                }
            }

            // Update order status
            $order->update([
                'status' => 'Completed',
                'completed_at' => now(),
            ]);

            return $execution->fresh(['order.patient', 'performedBy', 'consumables']);
        });
    }
}
