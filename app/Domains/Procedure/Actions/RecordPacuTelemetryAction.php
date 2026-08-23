<?php

namespace App\Domains\Procedure\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Procedure\Models\PacuRecoveryRecord;
use App\Domains\Procedure\Models\SurgicalBooking;
use Illuminate\Support\Facades\DB;

class RecordPacuTelemetryAction
{
    public function execute(SurgicalBooking $booking, array $scores, ?string $destinationWardId = null, ?string $notes = null): PacuRecoveryRecord
    {
        return DB::transaction(function () use ($booking, $scores, $destinationWardId, $notes) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $booking->tenant_id;

            $consciousness = intval($scores['consciousness_score'] ?? 2);
            $activity = intval($scores['activity_score'] ?? 2);
            $respiration = intval($scores['respiration_score'] ?? 2);
            $circulation = intval($scores['circulation_score'] ?? 2);
            $o2 = intval($scores['oxygen_saturation_score'] ?? 2);

            $totalAldrete = $consciousness + $activity + $respiration + $circulation + $o2;
            $dischargeReady = $totalAldrete >= 9;

            $record = PacuRecoveryRecord::create([
                'tenant_id' => $tenantId,
                'surgical_booking_id' => $booking->id,
                'recorded_by_id' => auth()->id() ?: $booking->lead_surgeon_id,
                'recorded_at' => now(),
                'consciousness_score' => $consciousness,
                'activity_score' => $activity,
                'respiration_score' => $respiration,
                'circulation_score' => $circulation,
                'oxygen_saturation_score' => $o2,
                'total_aldrete_score' => $totalAldrete,
                'discharge_ready' => $dischargeReady,
                'destination_ward_id' => $destinationWardId,
                'notes' => $notes,
            ]);

            if ($dischargeReady && $destinationWardId) {
                $booking->update(['status' => 'Completed']);
                $booking->order?->update(['status' => 'Completed', 'completed_at' => now()]);
            } else {
                $booking->update(['status' => 'PACU']);
            }

            return $record->fresh(['booking.order.patient', 'recordedBy', 'destinationWard']);
        });
    }
}
