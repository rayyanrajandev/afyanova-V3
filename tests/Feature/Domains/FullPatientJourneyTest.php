<?php

use App\Domains\Billing\Actions\RecordPaymentAction;
use App\Domains\Billing\Models\ChargeMasterItem;
use App\Domains\Clinical\Actions\RecordVitalsAction;
use App\Domains\Clinical\Actions\SignClinicalNoteAction;
use App\Domains\Clinical\Models\Allergy;
use App\Domains\Clinical\Models\ClinicalNote;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Pharmacy\Actions\DispenseMedicationAction;
use App\Domains\Pharmacy\Actions\PrescribeMedicationAction;
use App\Domains\Pharmacy\Actions\VerifyPrescriptionAction;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Scheduling\Actions\BookAppointmentAction;
use App\Domains\Scheduling\Actions\CheckInPatientAction;
use App\Domains\Scheduling\Actions\TransferQueueAction;
use App\Domains\Scheduling\Models\ProviderSchedule;

test('end-to-end outpatient healthcare journey runs flawlessly across all domains', function () {
    // -------------------------------------------------------------
    // SETUP: Tenant, Facility & Provider Environment
    // -------------------------------------------------------------
    $env = $this->setupTenantEnvironment();
    $tenant = $env['tenant'];
    $facility = $env['facility'];
    $doctor = $env['user'];
    $this->actingAs($doctor);

    // -------------------------------------------------------------
    // PHASE 1: PATIENT REGISTRATION & DEMOGRAPHICS
    // -------------------------------------------------------------
    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Grace',
        'last_name' => 'Shoo',
        'gender' => 'Female',
        'dob' => '1992-04-12',
        'phone' => '+255768112233',
        'email' => 'grace.shoo@example.com',
        'nida' => '19920412-10001-00002-33',
    ]);

    expect($patient->primary_mrn)->toStartWith('MRN-')
        ->and($patient->status)->toBe('Active');

    // Record Known Allergy to Penicillin
    Allergy::create([
        'patient_id' => $patient->id,
        'recorded_by' => $doctor->id,
        'allergen' => 'Penicillin',
        'allergen_type' => 'Drug',
        'reaction_severity' => 'Moderate',
        'status' => 'Active',
    ]);

    // -------------------------------------------------------------
    // PHASE 2: SCHEDULING, APPOINTMENT & CHECK-IN
    // -------------------------------------------------------------
    $today = now();
    ProviderSchedule::create([
        'provider_id' => $doctor->id,
        'facility_id' => $facility->id,
        'day_of_week' => $today->dayOfWeek,
        'start_time' => '07:00:00',
        'end_time' => '19:00:00',
        'is_active' => true,
    ]);

    $appointment = app(BookAppointmentAction::class)->execute([
        'patient_id' => $patient->id,
        'facility_id' => $facility->id,
        'provider_id' => $doctor->id,
        'scheduled_time' => $today->copy()->setTime(11, 0, 0),
        'duration_minutes' => 30,
        'appointment_type' => 'General OPD Consultation',
    ]);

    // Patient arrives at reception and checks in
    $ticket = app(CheckInPatientAction::class)->execute($appointment);

    expect($appointment->refresh()->status)->toBe('Checked-In')
        ->and($ticket->current_service_point)->toBe('Triage')
        ->and($ticket->encounter_id)->not->toBeNull();

    $encounter = $ticket->encounter;

    // Transfer from Triage to Doctor Consultation Room
    app(TransferQueueAction::class)->execute($ticket, 'Doctor Room 1');
    expect($ticket->refresh()->current_service_point)->toBe('Doctor Room 1');

    // -------------------------------------------------------------
    // PHASE 3: CLINICAL CONSULTATION, VITALS & SOAP NOTES
    // -------------------------------------------------------------
    // 1. Record Vitals
    $vital = app(RecordVitalsAction::class)->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'temperature_c' => 38.5,
        'heart_rate' => 88,
        'systolic_bp' => 118,
        'diastolic_bp' => 78,
        'weight_kg' => 65.0,
        'height_cm' => 168.0,
    ]);

    expect((float) $vital->bmi)->toBe(23.03);

    // 2. Draft SOAP Note
    $note = ClinicalNote::create([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'author_id' => $doctor->id,
        'note_type' => 'SOAP',
        'content' => [
            'subjective' => 'Patient has headache and body aches for 2 days.',
            'objective' => 'Febrile (38.5C). Throat normal.',
            'assessment' => 'Acute Febrile Illness - R/O Malaria.',
            'plan' => 'Prescribe analgesics and order mRDT.',
        ],
        'is_signed' => false,
    ]);

    // 3. Sign Note (Legally Locked)
    $signedNote = app(SignClinicalNoteAction::class)->execute($note);
    expect($signedNote->is_signed)->toBeTrue();

    // -------------------------------------------------------------
    // PHASE 4: E-PRESCRIBING & PHARMACY DISPENSING
    // -------------------------------------------------------------
    $paracetamol = MedicationFormulary::create([
        'generic_name' => 'Paracetamol',
        'brand_name' => 'Panadol',
        'form' => 'Tablet',
        'strength' => '500mg',
        'route' => 'PO',
        'drug_class' => 'Analgesic',
        'charge_code' => 'PHARM-PARACETAMOL-500',
        'is_active' => true,
    ]);
    ChargeMasterItem::create([
        'tenant_id' => $env['tenant']->id,
        'code' => 'PHARM-PARACETAMOL-500',
        'name' => 'Paracetamol 500mg',
        'category' => 'Pharmacy',
        'unit_price' => 150.00,
        'effective_from' => now()->subYear()->toDateString(),
    ]);

    $prescription = app(PrescribeMedicationAction::class)->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'medication_id' => $paracetamol->id,
        'dosage' => '1g',
        'frequency' => 'TDS',
        'duration_days' => 3,
        'route' => 'PO',
        'quantity' => 18,
        'instructions' => 'Take 2 tablets three times daily with water',
    ]);

    expect($prescription->status)->toBe('Pending');

    // Pharmacist verifies and dispenses
    app(VerifyPrescriptionAction::class)->execute($prescription);
    $dispenseEvent = app(DispenseMedicationAction::class)->execute($prescription, 18, 'Fully dispensed with counseling.');

    expect($prescription->refresh()->status)->toBe('Dispensed')
        ->and($dispenseEvent->quantity_dispensed)->toBe(18);

    // -------------------------------------------------------------
    // -------------------------------------------------------------
    // PHASE 5: CASHIER BILLING & DOUBLE-ENTRY LEDGER
    // -------------------------------------------------------------
    // The invoice was automatically generated and accumulated charges (15,000 Consultation + 2,700 Pharmacy)
    $invoice = $encounter->invoices()->first();
    expect($invoice->status)->toBe('Open')
        ->and((float) $invoice->total_amount)->toBe(17700.00)
        ->and($invoice->lineItems)->toHaveCount(2);

    // Settle invoice in full via Cashier (Tigo Pesa)
    $tx = app(RecordPaymentAction::class)->execute($invoice, 17700.00, 'TigoPesa');

    $invoice->refresh();
    expect($invoice->status)->toBe('Paid')
        ->and((float) $invoice->paid_amount)->toBe(17700.00);

    // Assert Double-Entry Ledger Zero Balance Integrity
    $totalDebits = (float) $tx->entries()->sum('debit');
    $totalCredits = (float) $tx->entries()->sum('credit');

    expect($totalDebits)->toBe(17700.00)
        ->and($totalCredits)->toBe(17700.00)
        ->and($totalDebits - $totalCredits)->toBe(0.0);
});
