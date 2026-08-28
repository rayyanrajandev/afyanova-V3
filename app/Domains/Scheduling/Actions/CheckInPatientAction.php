<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Billing\Actions\GenerateInvoiceAction;
use App\Domains\Billing\Services\ChargePriceResolver;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Support\Facades\DB;

class CheckInPatientAction
{
    public function __construct(
        protected StartEncounterAction $startEncounterAction,
        protected GenerateInvoiceAction $generateInvoiceAction,
        protected ChargePriceResolver $chargePriceResolver
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

            // Resolve Consultation Price from ChargeMasterItem single source of truth
            $chargeCode = match ($appointment->appointment_type) {
                'Specialist', 'Specialist Consultation' => 'CONSULT-SPEC',
                default => 'CONSULT-OPD',
            };

            try {
                $consultationPrice = $this->chargePriceResolver->priceFor($chargeCode);
            } catch (\Exception) {
                try {
                    $consultationPrice = $this->chargePriceResolver->priceFor('CONSULT-OPD');
                } catch (\Exception) {
                    $consultationPrice = 20000.00;
                }
            }

            // Upfront Prepaid Invoice for Consultation
            $this->generateInvoiceAction->execute(
                $encounter,
                'OPD Consultation - '.($appointment->appointment_type ?? 'General'),
                $consultationPrice,
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
                'status' => QueueTicketStatus::Waiting,
                'joined_queue_at' => now(),
            ]);
        });
    }
}
