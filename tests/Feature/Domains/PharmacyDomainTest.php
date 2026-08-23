<?php

use App\Domains\Billing\Models\ChargeMasterItem;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Clinical\Models\Allergy;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Pharmacy\Actions\DispenseMedicationAction;
use App\Domains\Pharmacy\Actions\PrescribeMedicationAction;
use App\Domains\Pharmacy\Actions\VerifyPrescriptionAction;
use App\Domains\Pharmacy\Exceptions\PharmacyException;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\Prescription;

test('e-prescribing creates pending prescription for active encounter', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Hassan',
        'last_name' => 'Mwinyi',
        'gender' => 'Male',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    $medication = MedicationFormulary::create([
        'generic_name' => 'Ibuprofen',
        'brand_name' => 'Brufen',
        'form' => 'Tablet',
        'strength' => '400mg',
        'route' => 'PO',
        'drug_class' => 'NSAID',
        'charge_code' => 'PHARM-IBUPROFEN-400',
        'is_active' => true,
    ]);
    ChargeMasterItem::create([
        'tenant_id' => $env['tenant']->id,
        'code' => 'PHARM-IBUPROFEN-400',
        'name' => 'Ibuprofen 400mg',
        'category' => 'Pharmacy',
        'unit_price' => 200.00,
        'effective_from' => now()->subYear()->toDateString(),
    ]);

    $prescribeAction = app(PrescribeMedicationAction::class);
    $prescription = $prescribeAction->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'medication_id' => $medication->id,
        'dosage' => '400mg',
        'frequency' => 'TDS',
        'duration_days' => 5,
        'route' => 'PO',
        'quantity' => 15,
        'instructions' => 'Take after meals',
    ]);

    expect($prescription)->toBeInstanceOf(Prescription::class)
        ->and($prescription->status)->toBe('Pending')
        ->and($prescription->quantity)->toBe(15);
});

test('e-prescribing blocks contraindicated drugs when patient has recorded allergy', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Maryam',
        'last_name' => 'Salim',
        'gender' => 'Female',
    ]);

    // Record an active allergy to Penicillin
    Allergy::create([
        'patient_id' => $patient->id,
        'recorded_by' => $user->id,
        'allergen' => 'Penicillin',
        'allergen_type' => 'Drug',
        'reaction_severity' => 'Severe',
        'clinical_manifestation' => 'Anaphylaxis',
        'status' => 'Active',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    // Medication belongs to Penicillin class
    $amoxil = MedicationFormulary::create([
        'generic_name' => 'Amoxicillin',
        'brand_name' => 'Amoxil',
        'form' => 'Capsule',
        'strength' => '500mg',
        'route' => 'PO',
        'drug_class' => 'Penicillin',
        'is_active' => true,
    ]);

    $prescribeAction = app(PrescribeMedicationAction::class);

    expect(fn () => $prescribeAction->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'medication_id' => $amoxil->id,
        'dosage' => '500mg',
        'frequency' => 'TDS',
        'duration_days' => 7,
        'route' => 'PO',
        'quantity' => 21,
    ]))->toThrow(PharmacyException::class);
});

test('pharmacist can verify and dispense medications', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Daudi',
        'last_name' => 'Mwakasege',
        'gender' => 'Male',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    $med = MedicationFormulary::create([
        'generic_name' => 'Paracetamol',
        'form' => 'Tablet',
        'strength' => '500mg',
        'route' => 'PO',
        'charge_code' => 'PHARM-PARACETAMOL-500',
        'is_active' => true,
    ]);
    ChargeMasterItem::firstOrCreate(
        ['tenant_id' => $env['tenant']->id, 'code' => 'PHARM-PARACETAMOL-500'],
        [
            'name' => 'Paracetamol 500mg',
            'category' => 'Pharmacy',
            'unit_price' => 150.00,
            'effective_from' => now()->subYear()->toDateString(),
        ]
    );

    $prescription = app(PrescribeMedicationAction::class)->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'medication_id' => $med->id,
        'dosage' => '500mg',
        'frequency' => 'QDS',
        'duration_days' => 3,
        'route' => 'PO',
        'quantity' => 12,
    ]);

    // 1. Cannot dispense before verification
    $dispenseAction = app(DispenseMedicationAction::class);
    expect(fn () => $dispenseAction->execute($prescription, 12))
        ->toThrow(PharmacyException::class);

    // 2. Verify prescription
    $verifyAction = app(VerifyPrescriptionAction::class);
    $verified = $verifyAction->execute($prescription);
    expect($verified->status)->toBe('Verified');

    // 3. Partial dispense
    $event1 = $dispenseAction->execute($prescription, 6, 'Dispensed first half');
    $prescription->refresh();
    expect($prescription->status)->toBe('Partially Dispensed')
        ->and($event1->quantity_dispensed)->toBe(6);

    // 4. Complete remaining dispense
    $event2 = $dispenseAction->execute($prescription, 6, 'Dispensed remaining');
    $prescription->refresh();
    expect($prescription->status)->toBe('Dispensed')
        ->and($prescription->dispenseEvents)->toHaveCount(2);
});

test('duplicate active prescriptions for same medication in active encounter are blocked', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Sara',
        'last_name' => 'Ali',
        'gender' => 'Female',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    $med = MedicationFormulary::create([
        'generic_name' => 'Paracetamol',
        'form' => 'Tablet',
        'strength' => '500mg',
        'route' => 'PO',
        'charge_code' => 'PHARM-PARACETAMOL-500',
        'is_active' => true,
    ]);
    ChargeMasterItem::firstOrCreate(
        ['tenant_id' => $env['tenant']->id, 'code' => 'PHARM-PARACETAMOL-500'],
        [
            'name' => 'Paracetamol 500mg',
            'category' => 'Pharmacy',
            'unit_price' => 150.00,
            'effective_from' => now()->subYear()->toDateString(),
        ]
    );

    $prescribeAction = app(PrescribeMedicationAction::class);
    // First prescription succeeds
    $prescribeAction->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'medication_id' => $med->id,
        'dosage' => '500mg',
        'frequency' => 'TDS',
        'duration_days' => 5,
        'route' => 'PO',
        'quantity' => 15,
    ]);

    // Second prescription for the same medication must throw PharmacyException
    expect(fn () => $prescribeAction->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'medication_id' => $med->id,
        'dosage' => '1000mg',
        'frequency' => 'BD',
        'duration_days' => 3,
        'route' => 'PO',
        'quantity' => 6,
    ]))->toThrow(PharmacyException::class);
});
