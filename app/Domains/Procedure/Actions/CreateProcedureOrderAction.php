<?php

namespace App\Domains\Procedure\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Procedure\Models\ProcedureOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateProcedureOrderAction
{
    public function execute(Encounter $encounter, string $procedureCatalogId, string $priority = 'Routine', ?string $indication = null): ProcedureOrder
    {
        $patient = $encounter->patient;
        if ($patient?->isDeceased()) {
            throw new InvalidArgumentException("Cannot order procedure. Patient {$patient->first_name} {$patient->last_name} is recorded as Deceased.");
        }
        if ($patient?->isMerged()) {
            throw new InvalidArgumentException("Cannot order procedure. Patient {$patient->first_name} {$patient->last_name} has been merged into {$patient->merged_into_patient_id}.");
        }

        return DB::transaction(function () use ($encounter, $procedureCatalogId, $priority, $indication) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $encounter->tenant_id;
            $catalog = ProcedureCatalog::findOrFail($procedureCatalogId);

            $orderNumber = 'PR-'.date('Y').'-'.strtoupper(Str::random(6));

            $order = ProcedureOrder::create([
                'tenant_id' => $tenantId,
                'order_number' => $orderNumber,
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'ordering_provider_id' => auth()->id() ?: $encounter->provider_id,
                'procedure_catalog_id' => $catalog->id,
                'priority' => $priority,
                'clinical_indication' => $indication,
                'status' => 'Ordered',
                'ordered_at' => now(),
            ]);

            // Auto-Billing Line Item Integration
            if ($catalog->standard_price > 0) {
                $invoice = Invoice::firstOrCreate(
                    [
                        'encounter_id' => $encounter->id,
                        'patient_id' => $encounter->patient_id,
                        'status' => 'Draft',
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'facility_id' => $encounter->facility_id,
                        'invoice_number' => 'INV-'.date('Ymd').'-'.strtoupper(Str::random(4)),
                        'invoice_date' => now()->toDateString(),
                        'total_amount' => 0.00,
                        'paid_amount' => 0.00,
                        'balance_due' => 0.00,
                    ]
                );

                InvoiceLineItem::create([
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoice->id,
                    'description' => "Clinical Procedure: {$catalog->name} ({$catalog->procedure_code})",
                    'category' => 'Procedure',
                    'quantity' => 1,
                    'unit_price' => $catalog->standard_price,
                    'total_price' => $catalog->standard_price,
                ]);

                $invoice->update([
                    'total_amount' => $invoice->items()->sum('total_price'),
                    'balance_due' => $invoice->items()->sum('total_price') - $invoice->paid_amount,
                ]);
            }

            return $order->fresh(['catalog', 'patient', 'orderingProvider']);
        });
    }
}
