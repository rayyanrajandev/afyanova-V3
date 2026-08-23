<?php

namespace App\Domains\Inpatient\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\MedicationAdministrationRecord;
use App\Domains\Inventory\Models\DdaRegisterLog;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Support\Facades\DB;

class AdministerMedicationAction
{
    public function execute(array $data): MedicationAdministrationRecord
    {
        return DB::transaction(function () use ($data) {
            $admission = Admission::where('id', $data['admission_id'])
                ->where('tenant_id', $data['tenant_id'])
                ->firstOrFail();

            $item = null;
            if (! empty($data['item_master_id'])) {
                $item = ItemMaster::where('id', $data['item_master_id'])
                    ->where('tenant_id', $data['tenant_id'])
                    ->first();
            }

            $itemName = $data['item_name'] ?? ($item ? $item->name : 'Unknown Medication');
            $doseQty = (float) ($data['dose_quantity'] ?? 1);
            $locationId = $data['location_id'] ?? null;
            $batchNumber = $data['batch_number'] ?? null;
            $expiryDate = $data['expiry_date'] ?? null;
            $unitCost = $item ? (float) $item->unit_cost_price : 0;
            $unitPrice = $item ? (float) $item->unit_selling_price : 0;
            $chargeAmount = (float) ($data['charge_amount'] ?? ($unitPrice * $doseQty));

            $isDda = (bool) ($data['is_dda_narcotic'] ?? ($item ? $item->is_dda_narcotic : false));
            $witnessUserId = $data['witness_by'] ?? null;

            // 1. Stock Deduction from Ward Cabinet (FEFO Batch Allocation)
            if ($locationId && $item && $data['status'] === 'Administered') {
                $balanceQuery = InventoryStockBalance::where('tenant_id', $data['tenant_id'])
                    ->where('location_id', $locationId)
                    ->where(function ($q) use ($item) {
                        $q->where('item_id', $item->id)
                            ->orWhere('medication_id', $item->id);
                        if (! empty($item->medication_id)) {
                            $q->orWhere('medication_id', $item->medication_id);
                        }
                    })
                    ->where('quantity_on_hand', '>', 0);

                if ($batchNumber) {
                    $balanceQuery->where('batch_number', $batchNumber);
                }

                // FEFO: Sort by expiry date ascending
                $stockRecord = $balanceQuery->orderBy('expiry_date', 'asc')->first();

                if ($stockRecord) {
                    $deductQty = min($doseQty, (float) $stockRecord->quantity_on_hand);
                    $balanceBefore = (float) $stockRecord->quantity_on_hand;
                    $stockRecord->quantity_on_hand = max(0, $balanceBefore - $deductQty);
                    $stockRecord->save();

                    $batchNumber = $stockRecord->batch_number;
                    $expiryDate = $stockRecord->expiry_date;

                    // 2. DDA Register Log if Controlled Narcotic
                    if ($isDda) {
                        DdaRegisterLog::create([
                            'tenant_id' => $data['tenant_id'],
                            'facility_id' => $admission->facility_id,
                            'item_id' => $item->id,
                            'batch_id' => $stockRecord->batch_id,
                            'encounter_id' => $admission->encounter_id,
                            'patient_id' => $admission->patient_id,
                            'prescriber_id' => $admission->admitting_doctor_id,
                            'administering_nurse_id' => $data['administered_by'],
                            'witness_user_id' => $witnessUserId,
                            'dose_administered' => $doseQty,
                            'balance_before' => $balanceBefore,
                            'balance_after' => (float) $stockRecord->quantity_on_hand,
                            'notes' => 'Administered via Inpatient e-MAR at Bed '.($admission->bed?->bed_number ?? 'Inpatient'),
                        ]);
                    }
                }
            }

            // 3. Automated Patient Invoice Line Item Creation (Private Hospital Billing)
            $isBilled = (bool) ($data['is_billed'] ?? true);
            if ($isBilled && $chargeAmount > 0 && $data['status'] === 'Administered') {
                $invoice = Invoice::where('tenant_id', $data['tenant_id'])
                    ->where('patient_id', $admission->patient_id)
                    ->where('status', 'Draft')
                    ->latest()
                    ->first();

                if (! $invoice) {
                    $invoice = Invoice::create([
                        'tenant_id' => $data['tenant_id'],
                        'facility_id' => $admission->facility_id,
                        'patient_id' => $admission->patient_id,
                        'encounter_id' => $admission->encounter_id,
                        'invoice_number' => 'INV-IPD-'.strtoupper(substr(uniqid(), -6)),
                        'status' => 'Draft',
                        'total_amount' => 0,
                        'paid_amount' => 0,
                        'issued_at' => now(),
                    ]);
                }

                InvoiceLineItem::create([
                    'tenant_id' => $data['tenant_id'],
                    'invoice_id' => $invoice->id,
                    'category' => 'Pharmacy',
                    'description' => $itemName.' ('.$doseQty.' '.($data['dose_unit'] ?? 'dose').' via '.($data['route'] ?? 'Oral').')',
                    'quantity' => (int) ceil($doseQty),
                    'unit_price' => $unitPrice > 0 ? $unitPrice : ($chargeAmount / max(1, $doseQty)),
                    'total_price' => $chargeAmount,
                ]);

                // Update invoice total
                $invoice->total_amount = $invoice->lineItems()->sum('total_price');
                $invoice->save();
            }

            // 4. Save Medication Administration Record (e-MAR)
            return MedicationAdministrationRecord::create([
                'tenant_id' => $data['tenant_id'],
                'facility_id' => $admission->facility_id,
                'admission_id' => $admission->id,
                'encounter_id' => $admission->encounter_id,
                'patient_id' => $admission->patient_id,
                'item_master_id' => $item ? $item->id : null,
                'medication_id' => $data['medication_id'] ?? null,
                'location_id' => $locationId,
                'item_name' => $itemName,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'dose_quantity' => $doseQty,
                'dose_unit' => $data['dose_unit'] ?? 'dose',
                'route' => $data['route'] ?? 'Oral',
                'frequency' => $data['frequency'] ?? 'STAT',
                'scheduled_time' => $data['scheduled_time'] ?? now(),
                'administered_at' => now(),
                'administered_by' => $data['administered_by'],
                'witness_by' => $witnessUserId,
                'witness_pin_verified' => ! empty($data['witness_pin_verified']),
                'status' => $data['status'] ?? 'Administered',
                'is_dda_narcotic' => $isDda,
                'is_billed' => $isBilled,
                'charge_amount' => $chargeAmount,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
