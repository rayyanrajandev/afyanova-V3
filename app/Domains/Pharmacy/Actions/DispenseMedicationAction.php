<?php

namespace App\Domains\Pharmacy\Actions;

use App\Domains\Pharmacy\Exceptions\PharmacyException;
use App\Domains\Pharmacy\Models\DispenseEvent;
use App\Domains\Pharmacy\Models\DispenseEventBatch;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class DispenseMedicationAction
{
    public function execute(Prescription $prescription, int $quantityToDispense, ?string $notes = null): DispenseEvent
    {
        if ($prescription->status !== 'Verified' && $prescription->status !== 'Partially Dispensed') {
            throw PharmacyException::prescriptionNotVerified();
        }

        $alreadyDispensed = $prescription->dispenseEvents()->sum('quantity_dispensed');
        $remaining = $prescription->quantity - $alreadyDispensed;

        if ($quantityToDispense > $remaining) {
            throw PharmacyException::dispenseQuantityExceeded($quantityToDispense, $remaining);
        }

        return DB::transaction(function () use ($prescription, $quantityToDispense, $remaining, $notes) {
            $user = auth()->user();
            $tenantId = $prescription->tenant_id ?? $user?->tenant_id;
            $facilityId = $prescription->encounter?->facility_id ?? $user?->facility_id;
            $userId = auth()->id() ?? $prescription->prescriber_id;

            // 1. FEFO Batch Allocation & Inventory Deduction (if inventory tracking exists)
            $availableBatches = InventoryBatch::where('medication_id', $prescription->medication_id)
                ->where('status', 'Active')
                ->where('current_quantity', '>', 0)
                ->whereDate('expiry_date', '>', now())
                ->orderBy('expiry_date', 'asc')
                ->lockForUpdate()
                ->get();

            $totalBatchesCount = InventoryBatch::where('medication_id', $prescription->medication_id)->count();

            // If batches are tracked for this medication, enforce strict inventory checks & deductions
            if ($totalBatchesCount > 0) {
                $totalAvailable = $availableBatches->sum('current_quantity');
                if ($totalAvailable < $quantityToDispense) {
                    $medName = $prescription->medication?->generic_name ?? 'Medication';
                    throw PharmacyException::insufficientStock($medName, $quantityToDispense, $totalAvailable);
                }
            }

            // 2. Create the Dispense Event
            $event = DispenseEvent::create([
                'tenant_id' => $tenantId,
                'prescription_id' => $prescription->id,
                'dispensed_by' => $userId,
                'quantity_dispensed' => $quantityToDispense,
                'pharmacist_notes' => $notes,
            ]);

            // 3. Deduct stock across batches in FEFO sequence
            $qtyNeeded = $quantityToDispense;
            foreach ($availableBatches as $batch) {
                if ($qtyNeeded <= 0) {
                    break;
                }

                $deduct = min($qtyNeeded, $batch->current_quantity);
                $prevQty = $batch->current_quantity;
                $newQty = $prevQty - $deduct;

                // Update batch quantity and status
                $batch->update([
                    'current_quantity' => $newQty,
                    'status' => $newQty === 0 ? 'Depleted' : 'Active',
                ]);

                // Create Dispense Event Batch linkage
                DispenseEventBatch::create([
                    'tenant_id' => $tenantId,
                    'dispense_event_id' => $event->id,
                    'batch_id' => $batch->id,
                    'quantity_dispensed' => $deduct,
                    'unit_price_at_dispense' => $batch->unit_selling_price,
                ]);

                // Record stock movement ledger
                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'medication_id' => $prescription->medication_id,
                    'batch_id' => $batch->id,
                    'movement_type' => 'Dispensed',
                    'quantity_change' => -$deduct,
                    'quantity_before' => $prevQty,
                    'quantity_after' => $newQty,
                    'reference_type' => 'DispenseEvent',
                    'reference_id' => $event->id,
                    'performed_by' => $userId,
                    'notes' => "Dispensed {$deduct} units for Rx #{$prescription->id} (FEFO batch {$batch->batch_number})",
                ]);

                $qtyNeeded -= $deduct;
            }

            // 4. Update Prescription Status
            $newRemaining = $remaining - $quantityToDispense;
            $prescription->update([
                'status' => $newRemaining === 0 ? 'Dispensed' : 'Partially Dispensed',
            ]);

            return $event;
        });
    }
}
