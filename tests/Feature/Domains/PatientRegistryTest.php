<?php

use App\Domains\Patient\Actions\MergePatientsAction;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Patient\Actions\SearchPatientsAction;
use App\Domains\Patient\Models\Patient;

test('patient can be registered with generated MRN and contact details', function () {
    $env = $this->setupTenantEnvironment();

    $action = app(RegisterPatientAction::class);
    $patient = $action->execute([
        'first_name' => 'Neema',
        'last_name' => 'Mbowe',
        'gender' => 'Female',
        'dob' => '1995-08-20',
        'phone' => '+255712345678',
        'email' => 'neema@example.com',
        'nida' => '19950820-12345-00001-22',
    ]);

    expect($patient)->toBeInstanceOf(Patient::class)
        ->and($patient->primary_mrn)->toStartWith('MRN-')
        ->and($patient->first_name)->toBe('Neema')
        ->and($patient->contacts)->toHaveCount(2)
        ->and($patient->identifiers)->toHaveCount(1)
        ->and($patient->identifiers->first()->type)->toBe('NIDA');
});

test('patient can be searched by MRN, name prefix, and phone number', function () {
    $env = $this->setupTenantEnvironment();

    $registerAction = app(RegisterPatientAction::class);
    $patient = $registerAction->execute([
        'first_name' => 'Baraka',
        'last_name' => 'Kimaro',
        'gender' => 'Male',
        'dob' => '1988-12-01',
        'phone' => '+255788990011',
    ]);

    $searchAction = app(SearchPatientsAction::class);

    // Search by MRN
    $byMrn = $searchAction->execute($patient->primary_mrn);
    expect($byMrn)->toHaveCount(1)
        ->and($byMrn->first()->id)->toBe($patient->id);

    // Search by partial name
    $byName = $searchAction->execute('Baraka');
    expect($byName)->toHaveCount(1)
        ->and($byName->first()->id)->toBe($patient->id);

    // Search by phone
    $byPhone = $searchAction->execute('+255788990011');
    expect($byPhone)->toHaveCount(1)
        ->and($byPhone->first()->id)->toBe($patient->id);
});

test('duplicate patients can be merged with pointer preserved', function () {
    $env = $this->setupTenantEnvironment();

    $registerAction = app(RegisterPatientAction::class);
    $patient1 = $registerAction->execute([
        'first_name' => 'Kassim',
        'last_name' => 'Majaliwa',
        'gender' => 'Male',
        'phone' => '+255755112233',
    ]);

    $patient2 = $registerAction->execute([
        'first_name' => 'Kassim',
        'last_name' => 'Majaliwa',
        'gender' => 'Male',
        'nida' => '19800101-99999-00001-11',
    ]);

    $mergeAction = app(MergePatientsAction::class);
    $winner = $mergeAction->execute($patient1, $patient2);

    $patient2->refresh();

    expect($winner->id)->toBe($patient1->id)
        ->and($patient2->status)->toBe('Merged')
        ->and($patient2->merged_into_patient_id)->toBe($patient1->id)
        ->and($winner->identifiers)->toHaveCount(1); // NIDA moved to winner
});
