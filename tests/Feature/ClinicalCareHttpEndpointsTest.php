<?php

use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Clinical\Models\ClinicalConsent;
use App\Domains\Clinical\Models\ClinicalReferral;
use App\Domains\Clinical\Models\PatientImmunization;
use App\Domains\Clinical\Models\PatientProblem;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Radiology\Models\RadiologyReport;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Hash;

/**
 * HTTP-layer coverage for the Phase 2 domain modules that previously
 * existed only as directly-invoked Action classes: consent, referral,
 * immunization, ANC, partograph, the problem list, medication
 * reconciliation, and radiology ordering/reporting. Each of these has its
 * own dedicated Action-level test elsewhere (Phase2ClinicalSafetyAndModulesTest);
 * this file exercises the controller/route/policy wiring that makes them
 * reachable at all.
 */
function buildClinicalCareFixture(): array
{
    $tenant = Tenant::create([
        'name' => 'Care Extensions Hospital',
        'slug' => 'care-ext-'.uniqid(),
        'domain' => 'care-ext-'.uniqid().'.local',
        'status' => 'active',
    ]);

    setTestTenantContext($tenant->id);

    $facility = Facility::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Wing',
        'code' => 'MAIN-'.uniqid(),
        'is_active' => true,
    ]);

    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Doctor', 'slug' => 'doctor']);

    $permissionSlugs = [
        'clinical.consent.record', 'clinical.referral.create', 'clinical.immunization.administer',
        'clinical.anc.record', 'clinical.partograph.record', 'clinical.problem-list.manage',
        'pharmacy.medication-reconciliation.record', 'radiology.order.create',
        'radiology.report.sign', 'radiology.report.amend',
    ];
    foreach ($permissionSlugs as $slug) {
        $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'domain' => 'Clinical']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    $user = User::create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Dr. Jane',
        'last_name' => 'Massawe',
        'email' => 'doctor-'.uniqid().'@test.local',
        'password_hash' => Hash::make('password123'),
        'status' => 'active',
    ]);

    $user->roleAssignments()->create(['role_id' => $role->id]);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Fatuma',
        'last_name' => 'Juma',
        'gender' => 'Female',
        'dob' => '1998-04-12',
        'facility_id' => $facility->id,
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'facility_id' => $facility->id,
        'department_id' => null,
        'provider_id' => $user->id,
        'encounter_type' => 'OPD',
    ]);

    return compact('tenant', 'facility', 'role', 'user', 'patient', 'encounter');
}

test('a clinician can record informed consent against an encounter', function () {
    $env = buildClinicalCareFixture();

    $this->actingAs($env['user'])
        ->post(route('clinical.consent.store', $env['encounter']), [
            'consent_type' => 'Surgical',
            'procedure_title' => 'Appendectomy',
            'explanation_of_risks' => 'Bleeding, infection, anesthesia risk explained in Swahili.',
            'signatory_name' => 'Fatuma Juma',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(ClinicalConsent::where('patient_id', $env['patient']->id)->count())->toBe(1);
});

test('recording consent is forbidden without the permission', function () {
    $env = buildClinicalCareFixture();
    $env['user']->roleAssignments()->first()->role->permissions()
        ->detach(Permission::where('slug', 'clinical.consent.record')->first()?->id);

    $this->actingAs($env['user'])
        ->post(route('clinical.consent.store', $env['encounter']), [
            'consent_type' => 'Surgical',
            'procedure_title' => 'Appendectomy',
            'explanation_of_risks' => 'Risks explained.',
            'signatory_name' => 'Fatuma Juma',
        ])
        ->assertForbidden();
});

test('a clinician can create an inter-facility referral', function () {
    $env = buildClinicalCareFixture();

    $this->actingAs($env['user'])
        ->post(route('clinical.referral.store', $env['encounter']), [
            'specialty_required' => 'Cardiology',
            'clinical_summary' => 'Suspected valve disease on auscultation.',
            'reason_for_referral' => 'Needs echocardiogram unavailable at this facility.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(ClinicalReferral::where('patient_id', $env['patient']->id)->count())->toBe(1);
});

test('a clinician can administer and record an immunization', function () {
    $env = buildClinicalCareFixture();

    $this->actingAs($env['user'])
        ->post(route('clinical.immunization.store', $env['encounter']), [
            'vaccine_code' => 'TT',
            'vaccine_name' => 'Tetanus Toxoid',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(PatientImmunization::where('patient_id', $env['patient']->id)->count())->toBe(1);
});

test('a midwife can record an ANC visit and a partograph entry', function () {
    $env = buildClinicalCareFixture();

    $this->actingAs($env['user'])
        ->post(route('clinical.anc.store', $env['encounter']), [
            'gravida' => 2,
            'para' => 1,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->actingAs($env['user'])
        ->post(route('clinical.partograph.store', $env['encounter']), [
            'cervical_dilation_cm' => 5.5,
            'fetal_heart_rate_bpm' => 140,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

test('a clinician can add and resolve a problem on the patient problem list', function () {
    $env = buildClinicalCareFixture();

    $this->actingAs($env['user'])
        ->post(route('clinical.problems.store', $env['patient']), [
            'icd10_code' => 'I10',
            'problem_name' => 'Essential Hypertension',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $problem = PatientProblem::where('patient_id', $env['patient']->id)->firstOrFail();
    expect($problem->status)->toBe('Active');

    $this->actingAs($env['user'])
        ->post(route('clinical.problems.resolve', $problem))
        ->assertRedirect();

    expect($problem->fresh()->status)->toBe('Resolved');
});

test('a pharmacist can record a medication reconciliation for a patient', function () {
    $env = buildClinicalCareFixture();

    $this->actingAs($env['user'])
        ->post(route('pharmacy.reconciliation.store', $env['patient']), [
            'stage' => 'Admission',
            'facility_id' => $env['facility']->id,
            'medications' => [
                ['medication_name' => 'Amlodipine 5mg', 'action_taken' => 'Continue'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

test('a doctor can order imaging, sign the report, and amend it', function () {
    $env = buildClinicalCareFixture();

    $this->actingAs($env['user'])
        ->post(route('radiology.orders.store', $env['encounter']), [
            'modality' => 'X-Ray',
            'procedure_name' => 'Chest X-Ray PA View',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $order = RadiologyOrder::where('patient_id', $env['patient']->id)->firstOrFail();

    $this->actingAs($env['user'])
        ->post(route('radiology.report.sign', $order), [
            'findings' => 'No acute cardiopulmonary abnormality.',
            'impression' => 'Normal chest X-ray.',
        ])
        ->assertRedirect();

    $report = RadiologyReport::where('radiology_order_id', $order->id)->firstOrFail();
    expect($report->is_signed)->toBeTrue();

    $this->actingAs($env['user'])
        ->post(route('radiology.report.amend', $report), [
            'impression' => 'Small nodule noted on repeat review, recommend follow-up CT.',
            'amendment_reason' => 'Second read by senior radiologist identified missed finding.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(RadiologyReport::where('radiology_order_id', $order->id)->count())->toBe(2)
        ->and($report->fresh()->is_deprecated)->toBeTrue();
});
