<?php

use App\Domains\Clinical\Actions\AmendClinicalNoteAction;
use App\Domains\Clinical\Actions\RecordVitalsAction;
use App\Domains\Clinical\Actions\SignClinicalNoteAction;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Clinical\Exceptions\ClinicalImmutabilityException;
use App\Domains\Clinical\Models\Allergy;
use App\Domains\Clinical\Models\ClinicalNote;
use App\Domains\Clinical\Models\ClinicalVital;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Patient\Actions\RegisterPatientAction;

test('clinical encounter can be started and workspace rendered', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $facility = $env['facility'];

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Fatuma',
        'last_name' => 'Said',
        'gender' => 'Female',
    ]);

    $startEncounter = app(StartEncounterAction::class);
    $encounter = $startEncounter->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $facility->id,
        'department_id' => null,
        'provider_id' => $user->id,
        'encounter_type' => 'OPD',
        'reason_for_visit' => 'Fever and chills',
    ]);

    expect($encounter)->toBeInstanceOf(Encounter::class)
        ->and($encounter->status)->toBe('In Progress')
        ->and($encounter->patient->id)->toBe($patient->id);

    // Test workspace controller rendering with eager loaded relationships
    $response = $this->actingAs($user)->get(route('encounters.workspace', $encounter->id));
    $response->assertStatus(200);
});

test('vitals can be recorded with BMI computation and range validations', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Juma',
        'last_name' => 'Selemani',
        'gender' => 'Male',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    $recordVitals = app(RecordVitalsAction::class);

    // Normal vitals recording
    $vital = $recordVitals->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'temperature_c' => 37.2,
        'heart_rate' => 75,
        'systolic_bp' => 120,
        'diastolic_bp' => 80,
        'weight_kg' => 70.0,
        'height_cm' => 175.0,
    ]);

    expect($vital)->toBeInstanceOf(ClinicalVital::class)
        ->and((float) $vital->bmi)->toBe(22.86);

    // Invalid systolic <= diastolic should throw
    expect(fn () => $recordVitals->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'systolic_bp' => 80,
        'diastolic_bp' => 120,
    ]))->toThrow(InvalidArgumentException::class);
});

test('clinical notes follow medico-legal signing and amendment immutability', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Zuhura',
        'last_name' => 'Hamisi',
        'gender' => 'Female',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    // 1. Create a draft SOAP note
    $note = ClinicalNote::create([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'author_id' => $user->id,
        'note_type' => 'SOAP',
        'content' => [
            'subjective' => 'Patient complains of cough for 3 days.',
            'objective' => 'Lungs clear on auscultation.',
            'assessment' => 'Upper respiratory tract infection.',
            'plan' => 'Hydration and rest.',
        ],
        'is_signed' => false,
    ]);

    expect($note->is_signed)->toBeFalse();

    // 2. Sign the note
    $signAction = app(SignClinicalNoteAction::class);
    $signedNote = $signAction->execute($note);

    expect($signedNote->is_signed)->toBeTrue()
        ->and($signedNote->signed_at)->not->toBeNull();

    // 3. Attempting to sign again throws ClinicalImmutabilityException
    expect(fn () => $signAction->execute($signedNote))
        ->toThrow(ClinicalImmutabilityException::class);

    // 4. Amend the signed note (addendum)
    $amendAction = app(AmendClinicalNoteAction::class);
    $amendedNote = $amendAction->execute($signedNote, [
        'subjective' => 'Patient reports fever started yesterday in addition to cough.',
        'objective' => 'Lungs clear.',
        'assessment' => 'URTI with mild fever.',
        'plan' => 'Paracetamol 500mg PO TDS.',
    ], 'Added fever symptom reported post-consultation');

    $signedNote->refresh();

    expect($signedNote->is_deprecated)->toBeTrue()
        ->and($amendedNote->is_amendment)->toBeTrue()
        ->and($amendedNote->amended_note_id)->toBe($signedNote->id)
        ->and($amendedNote->amendment_reason)->toBe('Added fever symptom reported post-consultation');
});

test('complete end-to-end tanzania consultation workflow persists correctly to database', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    // 1. Register Patient with Allergy
    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Amina',
        'last_name' => 'Kassim',
        'gender' => 'Female',
        'dob' => '1995-04-12',
        'blood_group' => 'B+',
    ]);

    Allergy::create([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'recorded_by' => $user->id,
        'allergen_type' => 'Medication',
        'allergen' => 'Penicillin',
        'severity' => 'Severe',
        'reaction' => 'Anaphylaxis',
    ]);

    // 2. Start OPD Encounter
    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'provider_id' => $user->id,
        'encounter_type' => 'OPD',
        'reason_for_visit' => 'Acute febrile illness',
    ]);

    // 3. Record Vitals in DB
    $vital = app(RecordVitalsAction::class)->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'temperature_c' => 38.8,
        'heart_rate' => 96,
        'systolic_bp' => 118,
        'diastolic_bp' => 74,
        'weight_kg' => 62.0,
        'height_cm' => 165.0,
    ]);

    // 4. Save SOAP Note via Controller (Tanzania Malaria Protocol)
    $response = $this->post(route('clinical.notes.store', $encounter->id), [
        'note_type' => 'SOAP',
        'content' => [
            'subjective' => 'Patient reports high fever with chills for 3 days.',
            'objective' => 'Febrile (T: 38.8°C). Conjunctival pallor mild. Chest clear.',
            'assessment' => 'Acute Uncomplicated P. falciparum Malaria (B50.9).',
            'plan' => '1. ALu 20/120mg 6-dose regimen. 2. Paracetamol 1g TDS.',
        ],
    ]);
    $response->assertSessionHasNoErrors();

    $persistedNote = ClinicalNote::where('encounter_id', $encounter->id)->first();
    expect($persistedNote)->not->toBeNull()
        ->and($persistedNote->content['subjective'])->toContain('high fever with chills')
        ->and($persistedNote->content['assessment'])->toContain('B50.9')
        ->and($persistedNote->is_signed)->toBeFalse();

    // 5. Sign the Note
    $signResponse = $this->post(route('clinical.notes.sign', $persistedNote->id));
    $signResponse->assertSessionHasNoErrors();
    $persistedNote->refresh();
    expect($persistedNote->is_signed)->toBeTrue()
        ->and($persistedNote->signed_at)->not->toBeNull();

    // 6. Verify Database Persistence across all tables
    $this->assertDatabaseHas('patients', [
        'id' => $patient->id,
        'first_name' => 'Amina',
        'last_name' => 'Kassim',
    ]);

    $this->assertDatabaseHas('allergies', [
        'patient_id' => $patient->id,
        'allergen' => 'Penicillin',
    ]);

    $this->assertDatabaseHas('clinical_vitals', [
        'encounter_id' => $encounter->id,
        'temperature_c' => 38.8,
    ]);

    $this->assertDatabaseHas('clinical_notes', [
        'id' => $persistedNote->id,
        'is_signed' => true,
    ]);
});
