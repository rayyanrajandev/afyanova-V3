<?php

namespace App\Domains\Pharmacy\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Billing\Services\ChargePriceResolver;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Exceptions\PharmacyException;
use App\Domains\Pharmacy\Models\DispenseEvent;
use App\Domains\Pharmacy\Models\DispenseEventBatch;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Support\Facades\DB;

class DispenseDirectOtcAction
{
    public function __construct(
        protected ChargePriceResolver $prices
    ) {}

    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = auth()->user();
            $tenantId = $user->tenant_id;
            $facilityId = $user->facility_id ?? Facility::first()?->id;

            // 1. Resolve Patient and Encounter
            $patientId = $data['patient_id'] ?? null;
            $encounterId = $data['encounter_id'] ?? null;

            if (! $patientId) {
                $walkinPatient = Patient::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'primary_mrn' => 'MRN-WALKIN',
                    ],
                    [
                        'registered_at_facility_id' => $facilityId,
                        'first_name' => 'Walk-in',
                        'last_name' => 'Customer',
                        'gender' => 'Other',
                        'dob' => '2000-01-01',
                        'status' => 'Active',
                    ]
                );
                $patientId = $walkinPatient->id;
            }

            if (! $encounterId) {
                $encounter = Encounter::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'patient_id' => $patientId,
                    'encounter_type' => 'Direct_Pharmacy_OTC',
                    'reason_for_visit' => $data['reason'] ?? 'Direct OTC Pharmacy Dispensing',
                    'status' => 'Completed',
                    'start_time' => now(),
                    'end_time' => now(),
                ]);
                $encounterId = $encounter->id;
            }

            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new PharmacyException("No medication items provided for OTC sale.");
            }

            $dispensedEvents = [];
            $invoiceLineItems = [];
            $totalAmount = 0.0;

            foreach ($items as $item) {
                $medication = MedicationFormulary::findOrFail($item['medication_id']);
                $qtyToDispense = (int) $item['quantity'];

                if ($qtyToDispense <= 0) {
                    throw new PharmacyException("Quantity must be at least 1 for {$medication->generic_name}.");
                }

                // FEFO Batch Allocation & Inventory Check
                $availableBatches = InventoryBatch::where('medication_id', $medication->id)
                    ->where('status', 'Active')
                    ->where('current_quantity', '>', 0)
                    ->whereDate('expiry_date', '>', now())
                    ->orderBy('expiry_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $totalStock = $availableBatches->sum('current_quantity');
                $hasBatches = InventoryBatch::where('medication_id', $medication->id)->count() > 0;

                if ($hasBatches && $totalStock < $qtyToDispense) {
                    throw PharmacyException::insufficientStock($medication->generic_name, $qtyToDispense, $totalStock);
                }

                // Create Dispense Event
                $event = DispenseEvent::create([
                    'tenant_id' => $tenantId,
                    'prescription_id' => null, // Direct OTC sale
                    'dispensed_by' => $user->id,
                    'quantity_dispensed' => $qtyToDispense,
                    'pharmacist_notes' => $item['instructions'] ?? ($data['notes'] ?? 'Direct Walk-in OTC Sale'),
                ]);

                // Deduct stock via FEFO
                $qtyNeeded = $qtyToDispense;
                $unitPrice = (float) ($item['unit_price'] ?? null);
                if (! $unitPrice || $unitPrice <= 0) {
                    $unitPrice = $medication->charge_code ? (float) $this->prices->priceFor($medication->charge_code) : 1000.0;
                }

                foreach ($availableBatches as $batch) {
                    if ($qtyNeeded <= 0) break;

                    $deduct = min($qtyNeeded, $batch->current_quantity);
                    $prevQty = $batch->current_quantity;
                    $newQty = $prevQty - $deduct;

                    $batch->update([
                        'current_quantity' => $newQty,
                        'status' => $newQty === 0 ? 'Depleted' : 'Active',
                    ]);

                    DispenseEventBatch::create([
                        'tenant_id' => $tenantId,
                        'dispense_event_id' => $event->id,
                        'batch_id' => $batch->id,
                        'quantity_dispensed' => $deduct,
                        'unit_price_at_dispense' => $batch->unit_selling_price ?? $unitPrice,
                    ]);

                    StockMovement::create([
                        'tenant_id' => $tenantId,
                        'facility_id' => $facilityId,
                        'medication_id' => $medication->id,
                        'batch_id' => $batch->id,
                        'movement_type' => 'Dispensed',
                        'quantity_change' => -$deduct,
                        'quantity_before' => $prevQty,
                        'quantity_after' => $newQty,
                        'reference_type' => 'DispenseEvent',
                        'reference_id' => $event->id,
                        'performed_by' => $user->id,
                        'notes' => "Direct OTC Dispense of {$deduct} units (Batch {$batch->batch_number})",
                    ]);

                    $qtyNeeded -= $deduct;
                }

                $lineTotal = $unitPrice * $qtyToDispense;
                $totalAmount += $lineTotal;

                $invoiceLineItems[] = [
                    'description' => "Pharmacy OTC: {$medication->generic_name} {$medication->strength} ({$qtyToDispense} {$medication->form}s)",
                    'category' => 'Pharmacy',
                    'quantity' => $qtyToDispense,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                ];

                $dispensedEvents[] = $event;
            }

            // Create Invoice
            $invoice = null;
            if ($totalAmount > 0) {
                $invoice = Invoice::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'patient_id' => $patientId,
                    'encounter_id' => $encounterId,
                    'invoice_number' => 'INV-OTC-' . date('Ymd') . '-' . rand(1000, 9999),
                    'total_amount' => $totalAmount,
                    'paid_amount' => 0,
                    'status' => 'Issued',
                    'issued_at' => now(),
                ]);

                foreach ($invoiceLineItems as $line) {
                    InvoiceLineItem::create([
                        'tenant_id' => $tenantId,
                        'invoice_id' => $invoice->id,
                        'description' => $line['description'],
                        'category' => $line['category'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'total_price' => $line['total_price'],
                    ]);
                }
            }

            // Close queue ticket if applicable
            if (! empty($data['ticket_id'])) {
                $ticket = QueueTicket::find($data['ticket_id']);
                if ($ticket) {
                    $ticket->update([
                        'status' => QueueTicketStatus::Completed,
                        'completed_at' => now(),
                    ]);
                }
            }

            return [
                'success' => true,
                'events' => $dispensedEvents,
                'invoice' => $invoice,
                'total_amount' => $totalAmount,
            ];
        });
    }
}
