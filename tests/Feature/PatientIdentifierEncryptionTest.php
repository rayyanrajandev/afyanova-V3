<?php

use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Patient\Actions\SearchPatientsAction;
use App\Domains\Patient\Models\PatientIdentifier;
use Illuminate\Support\Facades\DB;

test('a national ID is stored encrypted, not in plaintext', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Encrypted',
        'last_name' => 'Test',
        'gender' => 'Male',
        'nida' => '19900101-12345-00001-23',
    ]);

    $rawRow = DB::table('patient_identifiers')->where('patient_id', $patient->id)->first();

    expect($rawRow->identifier_value)->not->toBe('19900101-12345-00001-23')
        ->and($rawRow->identifier_lookup_hash)->toBe(PatientIdentifier::lookupHash('19900101-12345-00001-23'));

    // Eloquent transparently decrypts it back.
    $identifier = PatientIdentifier::where('patient_id', $patient->id)->first();
    expect($identifier->identifier_value)->toBe('19900101-12345-00001-23');
});

test('exact-match search by national ID still finds the patient', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Findable',
        'last_name' => 'ByNida',
        'gender' => 'Female',
        'nida' => '19850615-99999-00002-45',
    ]);

    $results = app(SearchPatientsAction::class)->execute('19850615-99999-00002-45');

    expect($results->pluck('id'))->toContain($patient->id);
});
