<?php

namespace App\Domains\Procedure\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Procedure\Models\OperatingSuite;
use App\Domains\Procedure\Models\ProcedureOrder;
use App\Domains\Procedure\Models\SurgicalBooking;
use App\Domains\Procedure\Models\WhoSurgicalChecklist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookSurgicalCaseAction
{
    public function execute(ProcedureOrder $order, string $suiteId, array $data): SurgicalBooking
    {
        return DB::transaction(function () use ($order, $suiteId, $data) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $order->tenant_id;
            $suite = OperatingSuite::findOrFail($suiteId);

            $bookingNumber = 'SURG-'.date('Y').'-'.strtoupper(Str::random(6));

            $booking = SurgicalBooking::create([
                'tenant_id' => $tenantId,
                'booking_number' => $bookingNumber,
                'procedure_order_id' => $order->id,
                'operating_suite_id' => $suite->id,
                'lead_surgeon_id' => $data['lead_surgeon_id'] ?? auth()->id(),
                'anesthetist_id' => $data['anesthetist_id'] ?? null,
                'scrub_nurse_id' => $data['scrub_nurse_id'] ?? null,
                'scheduled_start' => $data['scheduled_start'] ?? now()->addHours(1),
                'scheduled_end' => $data['scheduled_end'] ?? now()->addHours(3),
                'urgency' => $data['urgency'] ?? 'Elective',
                'status' => 'Scheduled',
            ]);

            // Initialize WHO Safety Checklist
            WhoSurgicalChecklist::create([
                'tenant_id' => $tenantId,
                'surgical_booking_id' => $booking->id,
                'sponge_and_needle_count_correct' => true,
                'specimens_labeled_correctly' => true,
            ]);

            $order->update([
                'status' => 'InProgress',
            ]);

            return $booking->fresh(['suite', 'leadSurgeon', 'anesthetist', 'scrubNurse', 'order.patient', 'whoChecklist']);
        });
    }
}
