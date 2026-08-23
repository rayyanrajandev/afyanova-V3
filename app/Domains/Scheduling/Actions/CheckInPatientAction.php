<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Billing\Actions\GenerateInvoiceAction;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Support\Facades\DB;

class CheckInPatientAction
{
    public function __construct(
        protected StartEncounterAction $startEncounterAction,
        protected GenerateInvoiceAction $generateInvoiceAction
    ) {}

    public function execute(Appointment $appointment): QueueTicket
    {
        return DB::transaction(function () use ($appointment) {

            $appointment->update(['status' => 'Checked-In']);

            // Create the initial encounter
            $encounter = $this->startEncounterAction->execute([
                'tenant_id' => $appointment->tenant_id,
                'patient_id' => $appointment->patient_id,
                'facility_id' => $appointment->facility_id,
                'department_id' => $appointment->department_id,
                'provider_id' => $appointment->provider_id,
                'encounter_type' => 'OPD',
                'reason_for_visit' => $appointment->notes,
            ]);

            // Upfront Prepaid Invoice for Consultation
            $this->generateInvoiceAction->execute(
                $encounter,
                'OPD Consultation - '.($appointment->appointment_type ?? 'General'),
                15000.00,
                'Consultation',
                1
            );

            // Generate ticket number (e.g. A-101)
            $ticketNumber = 'A-'.rand(100, 999);

            return QueueTicket::create([
                'tenant_id' => $appointment->tenant_id,
                'patient_id' => $appointment->patient_id,
                'facility_id' => $appointment->facility_id,
                'encounter_id' => $encounter->id,
                'ticket_number' => $ticketNumber,
                'priority' => 'Routine',
                'current_service_point' => 'Triage',
                'status' => 'Waiting',
                'joined_queue_at' => now(),
            ]);
        });
    }
}
