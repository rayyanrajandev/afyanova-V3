<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\ChargePriceResolver;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use App\Domains\Clinical\Models\Encounter;
use Illuminate\Support\Facades\DB;

class GenerateInvoiceAction
{
    public function __construct(
        protected ChargePriceResolver $prices,
        protected InvoiceNumberGenerator $invoiceNumbers,
    ) {}

    public function execute(Encounter $encounter, ?string $description = 'General OPD Consultation', ?float $amount = null, string $category = 'Consultation', int $quantity = 1, string $chargeCode = 'CONSULT-OPD'): Invoice
    {
        return DB::transaction(function () use ($encounter, $description, $amount, $category, $quantity, $chargeCode) {
            $invoice = Invoice::where('encounter_id', $encounter->id)
                ->whereIn('status', ['Open', 'Draft'])
                ->latest('created_at')
                ->first();

            if (! $invoice) {
                $invoice = Invoice::create([
                    'tenant_id' => $encounter->tenant_id,
                    'patient_id' => $encounter->patient_id,
                    'facility_id' => $encounter->facility_id,
                    'encounter_id' => $encounter->id,
                    'invoice_number' => $this->invoiceNumbers->generate(),
                    'status' => 'Open',
                    'total_amount' => 0.00,
                    'paid_amount' => 0.00,
                ]);
            }

            $unitPrice = $amount ?? $this->prices->priceFor($chargeCode);
            $lineTotal = $unitPrice * $quantity;

            $invoice->lineItems()->create([
                'tenant_id' => $encounter->tenant_id,
                'description' => $description,
                'category' => $category,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $lineTotal,
            ]);

            $newTotal = $invoice->lineItems()->sum('total_price');
            $status = ($invoice->paid_amount >= $newTotal && $newTotal > 0)
                ? 'Paid'
                : ($invoice->paid_amount > 0 ? 'Partially Paid' : 'Open');

            $invoice->update([
                'total_amount' => $newTotal,
                'status' => $status,
            ]);

            return $invoice->refresh();
        });
    }
}
