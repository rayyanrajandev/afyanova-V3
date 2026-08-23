<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Models\DdaRegisterLog;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordDdaAdministrationAction
{
    /**
     * Records controlled substance administration in the DDA register with dual-signature verification.
     */
    public function execute(
        string $facilityId,
        string $itemId,
        string $batchId,
        float $doseAdministered,
        float $doseWasted,
        ?string $encounterId = null,
        ?string $patientId = null,
        ?string $prescriberId = null,
        ?string $nurseId = null,
        ?string $witnessId = null,
        ?string $indication = null,
        ?string $notes = null
    ): DdaRegisterLog {
        $administeringId = $nurseId ?? auth()->id();

        if (empty($witnessId) || $administeringId === $witnessId) {
            throw new InvalidArgumentException('DDA Controlled Substance administration requires two distinct clinician signatures (administering clinician and verifying witness).');
        }

        if ($doseAdministered <= 0) {
            throw new InvalidArgumentException('Administered dose must be greater than zero.');
        }

        if ($patientId) {
            $patient = Patient::find($patientId);
            if ($patient?->isDeceased()) {
                throw new InvalidArgumentException('Cannot administer controlled substance to a deceased patient.');
            }
            if ($patient?->isMerged()) {
                throw new InvalidArgumentException('Cannot administer controlled substance on a merged patient record.');
            }
        }

        return DB::transaction(function () use (
            $facilityId, $itemId, $batchId, $doseAdministered, $doseWasted,
            $encounterId, $patientId, $prescriberId, $administeringId, $witnessId, $indication, $notes
        ) {
            $item = ItemMaster::findOrFail($itemId);
            $batch = InventoryBatch::where('id', $batchId)->lockForUpdate()->firstOrFail();
            $tenantId = $item->tenant_id;

            $totalDeducted = $doseAdministered + $doseWasted;

            if ($batch->current_quantity < (int) ceil($totalDeducted)) {
                throw InsufficientStockException::forBatch(
                    $item->item_name,
                    $batch->batch_number,
                    (int) ceil($totalDeducted),
                    $batch->current_quantity
                );
            }

            // Get last log balance or current batch balance
            $lastLog = DdaRegisterLog::where('item_id', $itemId)
                ->where('batch_id', $batchId)
                ->latest()
                ->first();

            $balanceBefore = $lastLog ? (float) $lastLog->balance_after : (float) $batch->current_quantity;
            $balanceAfter = max(0, $balanceBefore - $totalDeducted);

            $log = DdaRegisterLog::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'item_id' => $itemId,
                'batch_id' => $batchId,
                'encounter_id' => $encounterId,
                'patient_id' => $patientId,
                'prescriber_id' => $prescriberId ?? auth()->id(),
                'administering_nurse_id' => $administeringId,
                'witness_user_id' => $witnessId,
                'dose_administered' => $doseAdministered,
                'dose_wasted_discarded' => $doseWasted,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'indication' => $indication,
                'notes' => $notes,
            ]);

            // Decrement batch
            $batch->decrement('current_quantity', (int) ceil($totalDeducted));

            // Write stock movement
            StockMovement::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'medication_id' => $item->medication_id ?? '00000000000000000000000000',
                'batch_id' => $batchId,
                'movement_type' => 'Dispensed',
                'quantity_change' => -(int) ceil($totalDeducted),
                'quantity_before' => (int) $balanceBefore,
                'quantity_after' => (int) $balanceAfter,
                'reference_type' => 'DdaRegisterLog',
                'reference_id' => $log->id,
                'performed_by' => $administeringId ?? auth()->id() ?? User::first()?->id,
                'notes' => "DDA Controlled Drug Administration: {$indication}",
            ]);

            return $log->load(['item', 'batch', 'patient', 'prescriber', 'administeringNurse', 'witness']);
        });
    }
}
