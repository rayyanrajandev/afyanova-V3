<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Models\User;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Procedure\Models\ProcedureOrder;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Support\Facades\DB;

/**
 * Checks a patient directly into a service-point queue without an
 * appointment — creating the encounter and, depending on the target desk,
 * the matching lab/procedure order and cash invoice, all in one atomic
 * step.
 *
 * Extracted out of QueueController::checkInDirect() (was ~170 lines of
 * inline multi-step writes spanning five domains — Clinical, Laboratory,
 * Procedure, Billing, Scheduling — directly in the controller, the app's
 * clearest single instance of that pattern). Every value, branch, and
 * default matches the original exactly — one deliberate change: this
 * wraps the whole thing in DB::transaction(), which the original method
 * never had, despite every comparable multi-step Action in this codebase
 * (CreateLabOrderAction, CreateProcedureOrderAction, GenerateInvoiceAction,
 * AdministerMedicationAction) doing exactly that. Without it, a failure
 * partway through — the ticket-number generation throwing after the
 * invoice was already created, say — left a partial write behind; that
 * gap is closed here, not left in place for the sake of a purely
 * mechanical move.
 *
 * Does NOT yet reuse StartEncounterAction/GenerateInvoiceAction the way
 * the appointment-based CheckInPatientAction does — the two flows'
 * defaults (e.g. encounter status "In Progress" here vs whatever
 * StartEncounterAction sets) weren't verified identical, and collapsing
 * them without confirming that is exactly the kind of change that should
 * happen deliberately, not as a side effect of moving code.
 */
class CheckInPatientDirectlyAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(array $validated, User $user): QueueTicket
    {
        return DB::transaction(function () use ($validated, $user) {
            $facilityId = $user->facility_id ?? Facility::first()?->id;

            // 1. Create or link encounter
            $encounter = Encounter::create([
                'tenant_id' => $user->tenant_id,
                'facility_id' => $facilityId,
                'patient_id' => $validated['patient_id'],
                'encounter_type' => $validated['visit_type'],
                'reason_for_visit' => $validated['reason'] ?? $validated['visit_type'],
                'status' => 'In Progress',
                'start_time' => now(),
            ]);

            $lineItems = [];
            $totalAmount = 0;

            // 2. Direct Laboratory Order Creation
            if ($validated['service_point'] === 'Lab') {
                $labOrder = LabOrder::create([
                    'tenant_id' => $user->tenant_id,
                    'encounter_id' => $encounter->id,
                    'patient_id' => $validated['patient_id'],
                    'ordering_provider_id' => $user->id,
                    'order_number' => 'ORD-LAB-'.rand(1000, 9999),
                    'priority' => $validated['priority'] ?? 'Routine',
                    'status' => 'Ordered',
                    'ordered_at' => now(),
                    'clinical_notes' => $validated['reason'] ?? 'Direct Laboratory Walk-in Request',
                ]);

                $selectedTests = ! empty($validated['selected_lab_test_ids'])
                    ? LabTest::whereIn('id', $validated['selected_lab_test_ids'])->get()
                    : LabTest::where('name', 'like', '%Malaria%')->limit(1)->get();

                foreach ($selectedTests as $test) {
                    $testPrice = (float) ($test->price ?? 5000);
                    LabOrderItem::create([
                        'tenant_id' => $user->tenant_id,
                        'lab_order_id' => $labOrder->id,
                        'lab_test_id' => $test->id,
                        'price' => $testPrice,
                        'status' => 'Pending',
                    ]);

                    $lineItems[] = [
                        'description' => "Laboratory: {$test->name} ({$test->test_code})",
                        'category' => 'Laboratory',
                        'quantity' => 1,
                        'unit_price' => $testPrice,
                        'total_price' => $testPrice,
                    ];
                    $totalAmount += $testPrice;
                }
            }

            // 3. Direct Procedure / Injection Order Creation
            if ($validated['service_point'] === 'Procedure') {
                $catalog = ! empty($validated['procedure_catalog_id'])
                    ? ProcedureCatalog::find($validated['procedure_catalog_id'])
                    : (ProcedureCatalog::where('procedure_code', 'PROC-INJ-001')->first() ?? ProcedureCatalog::where('tier_level', 'Tier1_Minor')->first());

                if ($catalog) {
                    ProcedureOrder::create([
                        'tenant_id' => $user->tenant_id,
                        'encounter_id' => $encounter->id,
                        'patient_id' => $validated['patient_id'],
                        'procedure_catalog_id' => $catalog->id,
                        'order_number' => 'ORD-PRC-'.rand(1000, 9999),
                        'priority' => $validated['priority'] ?? 'Routine',
                        'status' => 'Ordered',
                        'clinical_indication' => $validated['reason'] ?? "Direct Walk-in {$catalog->name}",
                    ]);

                    // Determine price dynamically based on clinical workflow & sourcing
                    $isRevisit = in_array($validated['visit_type'], ['Treatment_Followup', 'Injection Revisit']);
                    $isExternalSindano = (($validated['medication_source'] ?? null) === 'PatientSupplied' || $validated['visit_type'] === 'Sindano_Nje');

                    if (! $isRevisit) {
                        if ($isExternalSindano) {
                            $procPrice = 2000.00; // Nursing administration & consumables fee for Sindano ya Nje
                            $description = "Procedure Service: External Medication Administration - Sindano ya Nje ({$catalog->name})";
                        } else {
                            $procPrice = (float) ($catalog->standard_price ?? $catalog->base_price ?? 3000.00);
                            $description = "Procedure Service: {$catalog->name}";
                        }

                        $lineItems[] = [
                            'description' => $description,
                            'category' => 'Procedure',
                            'quantity' => 1,
                            'unit_price' => $procPrice,
                            'total_price' => $procPrice,
                        ];
                        $totalAmount += $procPrice;
                    }
                }
            }

            // 4. Auto-Generate Invoice for Cash Payments
            $paymentMode = $validated['payment_mode'] ?? 'Cash';
            $isRevisit = in_array($validated['visit_type'], ['Treatment_Followup', 'Injection Revisit']);
            if ($totalAmount > 0 && $paymentMode === 'Cash' && ! $isRevisit) {
                $invoice = Invoice::create([
                    'tenant_id' => $user->tenant_id,
                    'facility_id' => $facilityId,
                    'patient_id' => $validated['patient_id'],
                    'encounter_id' => $encounter->id,
                    'invoice_number' => 'INV-'.date('Ymd').'-'.rand(1000, 9999),
                    'total_amount' => $totalAmount,
                    'paid_amount' => 0,
                    'status' => 'Issued',
                    'issued_at' => now(),
                ]);

                foreach ($lineItems as $item) {
                    InvoiceLineItem::create([
                        'tenant_id' => $user->tenant_id,
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'category' => $item['category'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                    ]);
                }
            }

            // 5. Generate Ticket Number (e.g. PRC-004, LAB-005)
            $todayCount = QueueTicket::whereDate('created_at', today())->count();
            $prefix = match ($validated['service_point']) {
                'Procedure' => 'PRC',
                'Lab' => 'LAB',
                'Pharmacy' => 'PHM',
                'Cashier' => 'CSH',
                'Doctor' => 'DOC',
                default => 'TRG',
            };
            $ticketNumber = sprintf('%s-%03d', $prefix, $todayCount + 1);

            return QueueTicket::create([
                'tenant_id' => $user->tenant_id,
                'facility_id' => $facilityId,
                'patient_id' => $validated['patient_id'],
                'encounter_id' => $encounter->id,
                'ticket_number' => $ticketNumber,
                'current_service_point' => $validated['service_point'],
                'priority' => $validated['priority'] ?? 'Routine',
                'status' => QueueTicketStatus::Waiting,
                'joined_queue_at' => now(),
            ]);
        });
    }
}
