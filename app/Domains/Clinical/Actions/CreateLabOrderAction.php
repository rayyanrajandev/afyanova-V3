<?php

namespace App\Domains\Clinical\Actions;

use App\Domains\Billing\Exceptions\InvoiceImmutabilityException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Models\LabTest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateLabOrderAction
{
    public function execute(Encounter $encounter, array $testIds, string $priority = 'Routine', ?string $clinicalNotes = null): LabOrder
    {
        return DB::transaction(function () use ($encounter, $testIds, $priority, $clinicalNotes) {
            $tests = LabTest::whereIn('id', $testIds)->get();

            if ($tests->isEmpty()) {
                throw new \InvalidArgumentException('No valid lab tests selected.');
            }

            // Check for active duplicate lab investigations in this encounter
            $existingActiveTestIds = LabOrderItem::whereHas('labOrder', function ($q) use ($encounter) {
                $q->where('encounter_id', $encounter->id)
                    ->whereIn('status', ['Ordered', 'Sample Collected', 'In Progress', 'Pending']);
            })->pluck('lab_test_id')->toArray();

            $duplicateTests = $tests->filter(fn ($t) => in_array($t->id, $existingActiveTestIds));

            if ($duplicateTests->isNotEmpty()) {
                $names = $duplicateTests->pluck('name')->implode(', ');
                throw new \InvalidArgumentException("Active investigation already exists for: {$names}. Duplicate order prevented.");
            }

            // Generate unique Order Number
            $datePrefix = date('Ymd');
            $countToday = LabOrder::whereDate('created_at', today())->count() + 1;
            $orderNumber = sprintf('LAB-%s-%03d', $datePrefix, $countToday);

            // 1. Create Lab Order
            $order = LabOrder::create([
                'order_number' => $orderNumber,
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'ordering_provider_id' => auth()->id() ?? $encounter->provider_id,
                'priority' => in_array($priority, ['STAT', 'Urgent', 'Routine']) ? $priority : 'Routine',
                'clinical_notes' => $clinicalNotes,
                'status' => 'Ordered',
                'ordered_at' => now(),
            ]);

            $totalLabCost = 0;

            // 2. Create Lab Order Items
            foreach ($tests as $test) {
                $barcode = 'BC-'.strtoupper(Str::random(8));
                LabOrderItem::create([
                    'lab_order_id' => $order->id,
                    'lab_test_id' => $test->id,
                    'price' => $test->price,
                    'status' => 'Pending',
                    'specimen_barcode' => $barcode,
                    'has_critical_value' => false,
                ]);

                $totalLabCost += $test->price;
            }

            // 3. Auto-Billing: Accrue billable line items to patient's active invoice
            $invoice = Invoice::where('encounter_id', $encounter->id)
                ->where('status', '!=', 'Paid')
                ->latest()
                ->first();

            if (! $invoice) {
                $invCount = Invoice::whereDate('created_at', today())->count() + 1;
                $invNumber = sprintf('INV-%s-%03d', $datePrefix, $invCount);

                $invoice = Invoice::create([
                    'invoice_number' => $invNumber,
                    'facility_id' => $encounter->facility_id,
                    'encounter_id' => $encounter->id,
                    'patient_id' => $encounter->patient_id,
                    'status' => 'Open',
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'due_date' => now()->addDays(7),
                ]);
            }

            if (! in_array($invoice->status, ['Open', 'Draft'], true)) {
                throw InvoiceImmutabilityException::cannotAddLineItemOnceLocked($invoice->id, $invoice->status);
            }

            foreach ($tests as $test) {
                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'category' => 'Lab',
                    'description' => sprintf('[LAB] %s (%s)', $test->name, $test->test_code),
                    'quantity' => 1,
                    'unit_price' => $test->price,
                    'total_price' => $test->price,
                ]);
            }

            // Re-calculate invoice total
            $invoiceTotal = InvoiceLineItem::where('invoice_id', $invoice->id)->sum('total_price');
            $invoice->update([
                'total_amount' => $invoiceTotal,
            ]);

            return $order->load(['items.labTest', 'orderingProvider', 'patient']);
        });
    }
}
