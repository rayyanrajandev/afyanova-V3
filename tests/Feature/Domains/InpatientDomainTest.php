<?php

use App\Domains\Inpatient\Actions\AdmitPatientAction;
use App\Domains\Inpatient\Actions\DischargePatientAction;
use App\Domains\Inpatient\Actions\TransferBedAction;
use App\Domains\Inpatient\Exceptions\InpatientException;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Patient\Actions\RegisterPatientAction;
use Inertia\Testing\AssertableInertia as Assert;

test('patient can be admitted to available bed and encounter marked ipd', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Khadija',
        'last_name' => 'Bakari',
        'gender' => 'Female',
    ]);

    $ward = Ward::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'name' => 'Female Medical Ward',
        'code' => 'FMW-01',
        'ward_type' => 'General',
        'gender_restriction' => 'FemaleOnly',
        'daily_base_rate' => 25000.00,
        'is_active' => true,
    ]);

    $bed = Bed::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'ward_id' => $ward->id,
        'bed_number' => 'FMW-BED-01',
        'bed_type' => 'Standard',
        'daily_rate_amount' => 25000.00,
        'status' => 'Available',
    ]);

    $admitAction = app(AdmitPatientAction::class);
    $admission = $admitAction->execute([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'ward_id' => $ward->id,
        'bed_id' => $bed->id,
        'admitting_doctor_id' => $env['user']->id,
        'admission_reason' => 'Acute severe asthma exacerbation',
        'provisional_diagnosis' => 'Severe Asthma',
    ]);

    expect($admission)->toBeInstanceOf(Admission::class)
        ->and($admission->status)->toBe('Admitted')
        ->and($admission->patient_id)->toBe($patient->id)
        ->and($admission->bed_id)->toBe($bed->id);

    // Bed must be marked Occupied
    $bed->refresh();
    expect($bed->status)->toBe('Occupied');

    // Encounter must be marked IPD
    expect($admission->encounter)->not->toBeNull()
        ->and($admission->encounter->encounter_type)->toBe('IPD');
});

test('admission fails when bed is already occupied', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $patient1 = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Patient',
        'last_name' => 'One',
        'gender' => 'Male',
    ]);

    $patient2 = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Patient',
        'last_name' => 'Two',
        'gender' => 'Male',
    ]);

    $ward = Ward::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'name' => 'Male Ward',
        'code' => 'MW-01',
        'ward_type' => 'General',
        'is_active' => true,
    ]);

    $bed = Bed::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'ward_id' => $ward->id,
        'bed_number' => 'MW-BED-01',
        'status' => 'Available',
    ]);

    $admitAction = app(AdmitPatientAction::class);
    // First admission succeeds
    $admitAction->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient1->id,
        'ward_id' => $ward->id,
        'bed_id' => $bed->id,
        'admitting_doctor_id' => $env['user']->id,
        'admission_reason' => 'Observation',
    ]);

    // Second admission to same bed must throw InpatientException
    expect(fn () => $admitAction->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient2->id,
        'ward_id' => $ward->id,
        'bed_id' => $bed->id,
        'admitting_doctor_id' => $env['user']->id,
        'admission_reason' => 'Second patient trying same bed',
    ]))->toThrow(InpatientException::class);
});

test('bed transfer releases source bed and claims destination bed', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Hamisi',
        'last_name' => 'Said',
        'gender' => 'Male',
    ]);

    $ward1 = Ward::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'name' => 'ICU',
        'code' => 'ICU-WARD',
        'ward_type' => 'ICU',
        'is_active' => true,
    ]);

    $ward2 = Ward::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'name' => 'General Ward',
        'code' => 'GEN-WARD',
        'ward_type' => 'General',
        'is_active' => true,
    ]);

    $bed1 = Bed::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'ward_id' => $ward1->id,
        'bed_number' => 'ICU-BED-01',
        'status' => 'Available',
    ]);

    $bed2 = Bed::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'ward_id' => $ward2->id,
        'bed_number' => 'GEN-BED-01',
        'status' => 'Available',
    ]);

    $admission = app(AdmitPatientAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'ward_id' => $ward1->id,
        'bed_id' => $bed1->id,
        'admitting_doctor_id' => $env['user']->id,
        'admission_reason' => 'Critical monitoring in ICU',
    ]);

    // Transfer from ICU Bed 1 to General Bed 2
    $transferAction = app(TransferBedAction::class);
    $transfer = $transferAction->execute($admission, [
        'to_bed_id' => $bed2->id,
        'reason' => 'Patient improved, step down to general ward',
        'transferred_by' => $env['user']->id,
    ]);

    expect($transfer)->not->toBeNull()
        ->and($transfer->from_bed_id)->toBe($bed1->id)
        ->and($transfer->to_bed_id)->toBe($bed2->id);

    // Bed 1 must be marked Cleaning
    $bed1->refresh();
    expect($bed1->status)->toBe('Cleaning');

    // Bed 2 must be marked Occupied
    $bed2->refresh();
    expect($bed2->status)->toBe('Occupied');

    // Admission must be updated
    $admission->refresh();
    expect($admission->bed_id)->toBe($bed2->id)
        ->and($admission->ward_id)->toBe($ward2->id);
});

test('discharging patient releases bed and closes admission and encounter', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Fatuma',
        'last_name' => 'Ally',
        'gender' => 'Female',
    ]);

    $ward = Ward::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'name' => 'Maternity',
        'code' => 'MAT-TEST',
        'ward_type' => 'Maternity',
        'is_active' => true,
    ]);

    $bed = Bed::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'ward_id' => $ward->id,
        'bed_number' => 'MAT-BED-01',
        'status' => 'Available',
    ]);

    $admission = app(AdmitPatientAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'ward_id' => $ward->id,
        'bed_id' => $bed->id,
        'admitting_doctor_id' => $env['user']->id,
        'admission_reason' => 'Postpartum observation',
    ]);

    // Discharge patient
    $dischargeAction = app(DischargePatientAction::class);
    $discharged = $dischargeAction->execute($admission, [
        'discharge_disposition' => 'Home',
        'discharge_summary' => 'Mother and baby well, discharged home.',
        'discharged_by' => $env['user']->id,
    ]);

    expect($discharged->status)->toBe('Discharged')
        ->and($discharged->discharged_at)->not->toBeNull()
        ->and($discharged->discharge_disposition)->toBe('Home');

    // Bed released to Cleaning
    $bed->refresh();
    expect($bed->status)->toBe('Cleaning');

    // Encounter closed
    expect($admission->encounter->fresh()->status)->toBe('Closed');
});

test('inpatient workspace renders wards beds and active census for authenticated user', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $this->get('/inpatient')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/InpatientWorkspace')
            ->has('wards')
            ->has('beds')
            ->has('activeAdmissions')
            ->has('dischargedAdmissions')
        );
});
