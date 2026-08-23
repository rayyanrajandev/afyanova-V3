<?php

use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Insurance\Actions\AdjudicateClaimAction;
use App\Domains\Insurance\Actions\GenerateClaimFromEncounterAction;
use App\Domains\Insurance\Actions\RequestPreAuthAction;
use App\Domains\Insurance\Actions\SubmitClaimBatchAction;
use App\Domains\Insurance\Actions\VerifyPolicyEligibilityAction;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Insurance\Models\InsuranceProvider;
use App\Domains\Insurance\Models\InsuranceScheme;
use App\Domains\Insurance\Models\PatientPolicy;
use App\Domains\Insurance\Models\PreAuthorization;
use App\Domains\Patient\Actions\RegisterPatientAction;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $env = $this->setupTenantEnvironment();
    $this->tenant = $env['tenant'];
    $this->facility = $env['facility'];
    $this->user = $env['user'];

    $this->patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Khadija',
        'last_name' => 'Massawe',
        'gender' => 'Female',
        'dob' => '1990-05-15',
    ]);

    $this->provider = InsuranceProvider::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'NHIF-TEST',
        'name' => 'NHIF Test Scheme',
        'provider_type' => 'NationalScheme',
        'is_active' => true,
    ]);

    $this->scheme = InsuranceScheme::create([
        'tenant_id' => $this->tenant->id,
        'insurance_provider_id' => $this->provider->id,
        'code' => 'NHIF-TEST-STD',
        'name' => 'NHIF Standard Test',
        'co_pay_type' => 'FixedAmount',
        'co_pay_amount' => 5000.00,
        'is_active' => true,
    ]);

    $this->policy = PatientPolicy::create([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $this->patient->id,
        'insurance_provider_id' => $this->provider->id,
        'insurance_scheme_id' => $this->scheme->id,
        'card_number' => '01-TEST-9921',
        'principal_member_name' => 'Khadija Massawe',
        'policy_start_date' => now()->subMonths(2)->toDateString(),
        'policy_expiry_date' => now()->addMonths(10)->toDateString(),
        'status' => 'Active',
        'biometric_verified' => false,
    ]);

    $this->encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $this->patient->id,
        'facility_id' => $this->facility->id,
        'department_id' => null,
        'provider_id' => $this->user->id,
        'encounter_type' => 'OPD',
        'reason_for_visit' => 'Suspected Malaria & Fever',
    ]);

    // Attach primary diagnosis
    Diagnosis::create([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $this->patient->id,
        'encounter_id' => $this->encounter->id,
        'diagnosed_by' => $this->user->id,
        'icd_10_code' => 'B54',
        'description' => 'Unspecified malaria',
        'type' => 'Primary',
        'certainty' => 'Confirmed',
    ]);
});

test('insurance policy can be biometrically verified and eligibility confirmed', function () {
    $this->actingAs($this->user);

    $action = app(VerifyPolicyEligibilityAction::class);
    $verifiedPolicy = $action->execute($this->policy, true);

    expect($verifiedPolicy->status)->toBe('Active')
        ->and($verifiedPolicy->biometric_verified)->toBeTrue()
        ->and($verifiedPolicy->verified_at)->not->toBeNull();
});

test('pre-authorization TAR code can be issued and tracked for specialized procedure', function () {
    $this->actingAs($this->user);

    $action = app(RequestPreAuthAction::class);
    $preAuth = $action->execute([
        'patient_policy_id' => $this->policy->id,
        'encounter_id' => $this->encounter->id,
        'procedure_description' => 'Echocardiogram & Cardiac Doppler',
        'requested_amount' => 150000.00,
        'approved_amount' => 150000.00,
        'auth_code' => 'TAR-2026-TEST01',
    ]);

    expect($preAuth)->toBeInstanceOf(PreAuthorization::class)
        ->and($preAuth->auth_code)->toBe('TAR-2026-TEST01')
        ->and($preAuth->status)->toBe('Approved')
        ->and(floatval($preAuth->approved_amount))->toEqual(150000.00);
});

test('generating a claim from an encounter executes scrubber rules and computes co-pay split', function () {
    $this->actingAs($this->user);

    $action = app(GenerateClaimFromEncounterAction::class);
    $claim = $action->execute($this->encounter, $this->policy);

    expect($claim)->toBeInstanceOf(InsuranceClaim::class)
        ->and($claim->scrubber_passed)->toBeTrue()
        ->and($claim->status)->toBe('Vetted')
        ->and(floatval($claim->co_pay_amount))->toEqual(5000.00)
        ->and($claim->items)->not->toBeEmpty();
});

test('claim scrubber catches missing ICD-10 diagnoses and flags draft status', function () {
    $this->actingAs($this->user);

    // Create encounter without diagnosis
    $emptyEncounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $this->patient->id,
        'facility_id' => $this->facility->id,
        'department_id' => null,
        'provider_id' => $this->user->id,
        'encounter_type' => 'OPD',
        'reason_for_visit' => 'Follow up',
    ]);

    $action = app(GenerateClaimFromEncounterAction::class);
    $claim = $action->execute($emptyEncounter, $this->policy);

    expect($claim->scrubber_passed)->toBeFalse()
        ->and($claim->status)->toBe('Draft')
        ->and($claim->scrubber_errors)->toContain('Missing ICD-10 clinical diagnosis on consultation encounter.');
});

test('vetted claims can be bundled into an electronic batch and submitted', function () {
    $this->actingAs($this->user);

    $claimAction = app(GenerateClaimFromEncounterAction::class);
    $claim = $claimAction->execute($this->encounter, $this->policy);

    $batchAction = app(SubmitClaimBatchAction::class);
    $batchNo = $batchAction->execute([$claim->id]);

    expect($batchNo)->toStartWith('BATCH-')
        ->and($claim->fresh()->status)->toBe('Submitted')
        ->and($claim->fresh()->batch_number)->toBe($batchNo)
        ->and($claim->fresh()->submitted_at)->not->toBeNull();
});

test('remittance adjudication records approved outcome and reconciles ledger balances', function () {
    $this->actingAs($this->user);

    $claimAction = app(GenerateClaimFromEncounterAction::class);
    $claim = $claimAction->execute($this->encounter, $this->policy);

    $adjudicateAction = app(AdjudicateClaimAction::class);
    $adjudicatedClaim = $adjudicateAction->execute($claim, 'Approved', 10000.00, 'Remittance processed per bank advice');

    expect($adjudicatedClaim->status)->toBe('Approved')
        ->and(floatval($adjudicatedClaim->approved_amount))->toEqual(10000.00)
        ->and($adjudicatedClaim->adjudicated_at)->not->toBeNull();
});

test('insurance workspace renders correctly with queues and financial metrics', function () {
    $this->actingAs($this->user);

    $claimAction = app(GenerateClaimFromEncounterAction::class);
    $claimAction->execute($this->encounter, $this->policy);

    $response = $this->get(route('insurance.workspace'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/InsuranceWorkspace')
            ->has('claimsQueue')
            ->has('submittedClaims')
            ->has('preAuthorizations')
            ->has('patientPolicies')
            ->has('providers')
            ->has('metrics')
        );
});
