<?php

namespace App\Domains\Pharmacy\Actions;

use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReceiveStockBatchAction
{
    public function execute(array $data): InventoryBatch
    {
        // Dropped the Tenant::first()/global Facility::first() tail
        // fallbacks: silently receiving stock under an arbitrary other
        // tenant/facility when neither the caller nor the acting user
        // supplies one is a landmine, not a safety net.
        $tenantId = $data['tenant_id'] ?? auth()->user()?->tenant_id;
        $facilityId = $data['facility_id'] ?? auth()->user()?->facility_id ?? Facility::where('tenant_id', $tenantId)->first()?->id;
        $userId = $data['performed_by'] ?? auth()->id();

        if (empty($data['medication_id'])) {
            throw new InvalidArgumentException('Medication ID is required for stock receipt.');
        }

        if (empty($data['batch_number'])) {
            throw new InvalidArgumentException('Batch number is required.');
        }

        if (empty($data['expiry_date'])) {
            throw new InvalidArgumentException('Expiry date is required for batch tracking.');
        }

        $quantity = (int) ($data['quantity'] ?? $data['initial_quantity'] ?? 0);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity received must be greater than zero.');
        }

        return DB::transaction(function () use ($data, $tenantId, $facilityId, $userId, $quantity) {
            // 1. Calculate previous total stock for medication before this intake
            $medication = MedicationFormulary::findOrFail((string) $data['medication_id']);
            $prevStock = $medication->total_stock_on_hand;

            // 2. Create the inventory batch record
            $batch = InventoryBatch::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'medication_id' => $data['medication_id'],
                'batch_number' => trim($data['batch_number']),
                'barcode' => $data['barcode'] ?? null,
                'manufacture_date' => $data['manufacture_date'] ?? null,
                'expiry_date' => $data['expiry_date'],
                'initial_quantity' => $quantity,
                'current_quantity' => $quantity,
                'unit_cost' => $data['unit_cost'] ?? 0.00,
                'unit_selling_price' => $data['unit_selling_price'] ?? 0.00,
                'supplier_name' => $data['supplier_name'] ?? 'MSD (Medical Stores Department)',
                'status' => 'Active',
                'notes' => $data['notes'] ?? null,
            ]);

            // 3. Write immutable stock movement ledger entry
            StockMovement::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'medication_id' => $data['medication_id'],
                'batch_id' => $batch->id,
                'movement_type' => 'Received',
                'quantity_change' => $quantity,
                'quantity_before' => $prevStock,
                'quantity_after' => $prevStock + $quantity,
                'reference_type' => 'GoodsReceipt',
                'reference_id' => $batch->id,
                'performed_by' => $userId,
                'notes' => $data['notes'] ?? "Received batch {$batch->batch_number} from {$batch->supplier_name}",
            ]);

            return $batch;
        });
    }
}
