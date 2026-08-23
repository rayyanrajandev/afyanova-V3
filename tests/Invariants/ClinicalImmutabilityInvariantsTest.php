<?php

use App\Domains\Clinical\Actions\AmendVitalsAction;
use App\Domains\Clinical\Actions\RecordVitalsAction;
use App\Domains\Clinical\Actions\SignClinicalNoteAction;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Clinical\Exceptions\ClinicalImmutabilityException;
use App\Domains\Clinical\Models\ClinicalNote;
use App\Domains\Patient\Actions\RegisterPatientAction;

function buildActiveEncounterForInvariants(): array
{
    $env = test()->setupTenantEnvironment();
    test()->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Invariant',
        'last_name' => 'Clinical',
        'gender' => 'Female',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    return compact('env', 'patient', 'encounter');
}

test('a signed clinical note cannot be updated outside its Amend action', function () {
    ['encounter' => $encounter, 'patient' => $patient] = buildActiveEncounterForInvariants();

    $note = ClinicalNote::create([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'author_id' => auth()->id(),
        'note_type' => 'SOAP',
        'content' => ['subjective' => 'Initial complaint'],
        'is_signed' => false,
    ]);

    app(SignClinicalNoteAction::class)->execute($note);
    $note->refresh();
    expect($note->is_signed)->toBeTrue();

    expect(fn () => $note->update(['content' => ['subjective' => 'Tampered']]))
        ->toThrow(ClinicalImmutabilityException::class);
});

test('a finalized vital reading cannot be updated in place', function () {
    ['encounter' => $encounter, 'patient' => $patient] = buildActiveEncounterForInvariants();

    $vital = app(RecordVitalsAction::class)->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'temperature_c' => 37.0,
        'heart_rate' => 80,
    ]);

    expect(fn () => $vital->update(['temperature_c' => 40.0]))
        ->toThrow(ClinicalImmutabilityException::class);
});

test('amending a vital deprecates the original without violating the guard', function () {
    ['encounter' => $encounter, 'patient' => $patient] = buildActiveEncounterForInvariants();

    $vital = app(RecordVitalsAction::class)->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'temperature_c' => 37.0,
        'heart_rate' => 80,
    ]);

    $amended = app(AmendVitalsAction::class)
        ->execute($vital, ['temperature_c' => 38.5, 'heart_rate' => 90], 'Transcription error on initial entry');

    $vital->refresh();
    expect($vital->is_deprecated)->toBeTrue()
        ->and($amended->is_amendment)->toBeTrue()
        ->and($amended->amended_vital_id)->toBe($vital->id);
});
