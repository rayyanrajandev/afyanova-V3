<?php

namespace App\Domains\Inpatient\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\MedicationAdministrationRecord;
use App\Domains\Inventory\Models\DdaRegisterLog;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Support\Facades\DB;

class AdministerMedicationAction
{
    public function __construct(
        protected InvoiceNumberGenerator $invoiceNumbers,
    ) {}

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
                // batch_number and expiry_date live on inventory_batches, not
                // inventory_stock_balances (confirmed against the actual
                // schema — this table only has quantity/location/medication/
                // batch_id). A real SQL join for filtering/sorting on those
                // fields collides with BelongsToTenant's own global scope —
                // it adds an unqualified `tenant_id = ?` clause, ambiguous
                // the moment a second tenant_id-bearing table is joined in
                // (exactly the failure mode that trait's own code comment
                // warns about). A ward cabinet's candidate batches for one
                // medication are always few, so fetching them and sorting in
                // PHP sidesteps the join entirely rather than touching that
                // shared trait.
                $stockRecord = InventoryStockBalance::where('tenant_id', $data['tenant_id'])
                    ->where('location_id', $locationId)
                    ->where(function ($q) use ($item) {
                        // inventory_stock_balances has no item_id column —
                        // only medication_id. Match either the item's own id
                        // directly (ItemMaster/MedicationFormulary share the
                        // same id space for pharmaceuticals) or, if the item
                        // links to a separate medication record, that id too.
                        $q->where('medication_id', $item->id);
                        if (! empty($item->medication_id)) {
                            $q->orWhere('medication_id', $item->medication_id);
                        }
                    })
                    ->where('quantity_on_hand', '>', 0)
                    // Locks every candidate balance row for this medication
                    // at this location, for the duration of the enclosing
                    // DB::transaction(). Without this, two concurrent
                    // administrations against the same ward-cabinet balance
                    // can both read the same quantity_on_hand before either
                    // writes, both deduct from it, and leave the row
                    // overdrawn or negative — the same read-then-write race
                    // DispenseMedicationAction (the live pharmacy dispense
                    // path) already guards against with lockForUpdate() on
                    // InventoryBatch. Matched here on
                    // InventoryStockBalance since that's the row this method
                    // actually reads-then-decrements (quantity_on_hand,
                    // below); the eager-loaded `batch` relation isn't
                    // written to, so it doesn't need its own lock.
                    ->lockForUpdate()
                    ->with('batch')
                    ->get()
                    ->when(
                        $batchNumber,
                        fn ($balances) => $balances->filter(fn ($b) => $b->batch?->batch_number === $batchNumber)
                    )
                    // FEFO: earliest expiry first; a balance with no linked
                    // batch (or no expiry set) sorts last rather than
                    // crashing the comparison.
                    ->sortBy(fn ($b) => $b->batch?->expiry_date?->timestamp ?? PHP_INT_MAX)
                    ->first();

                if ($stockRecord) {
                    $deductQty = min($doseQty, (float) $stockRecord->quantity_on_hand);
                    $balanceBefore = (float) $stockRecord->quantity_on_hand;
                    $stockRecord->quantity_on_hand = max(0, $balanceBefore - $deductQty);
                    $stockRecord->save();

                    // Same reason as the join above: these live on the
                    // related InventoryBatch, not on $stockRecord itself.
                    // Previously always silently null — the e-MAR and DDA
                    // register entries this data feeds got no batch/expiry
                    // traceability despite a specific batch actually being
                    // allocated and deducted.
                    $batchNumber = $stockRecord->batch?->batch_number ?? $batchNumber;
                    $expiryDate = $stockRecord->batch?->expiry_date ?? $expiryDate;

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
                        'invoice_number' => $this->invoiceNumbers->generate(),
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
