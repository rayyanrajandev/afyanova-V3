<?php

namespace App\Domains\Procedure\Actions;

use App\Domains\Clinical\Models\Encounter;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Procedure\Models\ProcedureOrder;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Tenancy\Models\Facility;

/**
 * Backfills an Encounter and ProcedureOrder for any Procedure-queue ticket
 * that reached the Procedure service point without either — e.g. a direct
 * walk-in routed straight to a procedure desk, bypassing the doctor
 * consultation flow that would normally create both. Idempotent: a ticket
 * whose encounter already has an active order is left alone.
 *
 * Extracted out of ProcedureWorkspaceController::index() (was ~45 lines
 * of inline multi-step writes in a GET handler). Note this doesn't fully
 * fix the underlying issue it came from: it's still invoked on every page
 * load, meaning a read request performs writes and does a scan over every
 * waiting ticket on each visit. The correct home for this invariant is
 * wherever a QueueTicket actually gets routed to the Procedure service
 * point (most likely TransferQueueAction in the Scheduling domain) —
 * enforcing it once, at the point the ticket is created/routed, rather
 * than lazily re-checking on every page view. That move needs its own
 * look at the queue-routing flow and is deliberately out of scope here;
 * this extraction at least makes the logic independently testable and out
 * of the controller in the meantime.
 */
class EnsureProcedureOrdersForWaitingTicketsAction
{
    public function execute(?string $facilityIdFallback = null): void
    {
        $waitingTickets = QueueTicket::where('current_service_point', 'Procedure')
            ->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])
            ->get();

        if ($waitingTickets->isEmpty()) {
            return;
        }

        $defaultCatalog = ProcedureCatalog::where('name', 'like', '%Injection%')->first()
            ?? ProcedureCatalog::where('tier_level', 'Tier1_Minor')->first();

        foreach ($waitingTickets as $ticket) {
            $this->ensureOrderForTicket($ticket, $defaultCatalog, $facilityIdFallback);
        }
    }

    private function ensureOrderForTicket(QueueTicket $ticket, ?ProcedureCatalog $defaultCatalog, ?string $facilityIdFallback): void
    {
        $encounterId = $ticket->encounter_id;

        if (! $encounterId) {
            $encounter = Encounter::where('patient_id', $ticket->patient_id)
                ->whereIn('status', ['Arrived', 'InProgress', 'In_Progress', 'In Progress'])
                ->latest()
                ->first();

            if (! $encounter) {
                $encounter = Encounter::create([
                    'tenant_id' => $ticket->tenant_id,
                    'facility_id' => $ticket->facility_id ?? $facilityIdFallback ?? Facility::first()?->id,
                    'patient_id' => $ticket->patient_id,
                    'encounter_type' => 'Procedure',
                    'reason_for_visit' => 'Procedure / Injection Care',
                    'status' => 'InProgress',
                    'start_time' => now(),
                ]);
            }

            $ticket->update(['encounter_id' => $encounter->id]);
            $encounterId = $encounter->id;
        }

        $hasOrder = ProcedureOrder::where('encounter_id', $encounterId)
            ->whereIn('status', ['Ordered', 'InProgress', 'In_Progress'])
            ->exists();

        if (! $hasOrder && $defaultCatalog && $encounterId) {
            ProcedureOrder::create([
                'tenant_id' => $ticket->tenant_id,
                'encounter_id' => $encounterId,
                'patient_id' => $ticket->patient_id,
                'procedure_catalog_id' => $defaultCatalog->id,
                'order_number' => 'ORD-'.rand(1000, 9999),
                'priority' => $ticket->priority ?? 'Routine',
                'status' => 'Ordered',
                'clinical_indication' => 'Direct Walk-in Procedure / Nursing Queue Ticket '.$ticket->ticket_number,
            ]);
        }
    }
}
