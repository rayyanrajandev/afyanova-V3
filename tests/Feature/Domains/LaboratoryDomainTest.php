<?php

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Clinical\Actions\CreateLabOrderAction;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Laboratory\Actions\CollectSpecimenAction;
use App\Domains\Laboratory\Actions\RecordLabResultsAction;
use App\Domains\Laboratory\Actions\VerifyLabResultsAction;
use App\Domains\Patient\Actions\RegisterPatientAction;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $env = $this->setupTenantEnvironment();
    $this->tenant = $env['tenant'];
    $this->facility = $env['facility'];
    $this->user = $env['user'];

    $this->patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Jane',
        'last_name' => 'Mwenda',
        'gender' => 'Female',
        'dob' => '1992-04-10',
    ]);

    $this->encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $this->tenant->id,
        'patient_id' => $this->patient->id,
        'facility_id' => $this->facility->id,
        'department_id' => null,
        'provider_id' => $this->user->id,
        'encounter_type' => 'OPD',
        'reason_for_visit' => 'Fever and chills',
    ]);

    // Create standard test catalog
    $this->fbpTest = LabTest::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'test_code' => 'LAB-FBP-TEST'],
        [
            'name' => 'Full Blood Picture',
            'category' => 'Hematology',
            'specimen_type' => 'Whole Blood (EDTA)',
            'price' => 15000.00,
            'parameters' => [
                ['name' => 'Hemoglobin (Hb)', 'unit' => 'g/dL', 'min' => 12.0, 'max' => 17.5, 'panic_low' => 7.0, 'panic_high' => 20.0],
                ['name' => 'Platelets', 'unit' => 'x10^9/L', 'min' => 150, 'max' => 450, 'panic_low' => 50, 'panic_high' => 1000],
            ],
            'is_active' => true,
        ]
    );
});

test('a doctor can place a lab order which automatically creates billable invoice items', function () {
    $this->actingAs($this->user);

    $action = app(CreateLabOrderAction::class);
    $order = $action->execute(
        $this->encounter,
        [$this->fbpTest->id],
        'STAT',
        'Severe acute fatigue, investigate anemia'
    );

    expect($order)->toBeInstanceOf(LabOrder::class)
        ->and($order->priority)->toBe('STAT')
        ->and($order->status)->toBe('Ordered')
        ->and($order->items)->toHaveCount(1);

    $item = $order->items->first();
    expect(floatval($item->price))->toEqual(15000.00)
        ->and($item->status)->toBe('Pending')
        ->and($item->specimen_barcode)->not->toBeEmpty();

    // Verify Auto-Billing Integration
    $invoice = Invoice::where('encounter_id', $this->encounter->id)->first();
    expect($invoice)->not->toBeNull()
        ->and(floatval($invoice->total_amount))->toBeGreaterThanOrEqual(15000.00);

    $lineItem = InvoiceLineItem::where('invoice_id', $invoice->id)
        ->where('description', 'like', '%Full Blood Picture%')
        ->first();

    expect($lineItem)->not->toBeNull()
        ->and(floatval($lineItem->unit_price))->toEqual(15000.00)
        ->and(floatval($lineItem->total_price))->toEqual(15000.00);
});

test('entering results with parameter breaching panic bounds raises critical alert', function () {
    $this->actingAs($this->user);

    $createAction = app(CreateLabOrderAction::class);
    $order = $createAction->execute($this->encounter, [$this->fbpTest->id], 'STAT');
    $item = $order->items->first();

    $enterResultsAction = app(RecordLabResultsAction::class);

    // Hemoglobin of 6.2 is below panic_low threshold (7.0)
    $updatedItem = $enterResultsAction->execute($item, [
        'Hemoglobin (Hb)' => '6.2',
        'Platelets' => '220',
    ], 'Severe microcytic anemia observed');

    expect($updatedItem->status)->toBe('Completed')
        ->and($updatedItem->has_critical_value)->toBeTrue()
        ->and($updatedItem->critical_value_alerted_at)->not->toBeNull()
        ->and($updatedItem->labOrder->status)->toBe('Completed');
});

test('entering normal results completes order without critical panic flag', function () {
    $this->actingAs($this->user);

    $createAction = app(CreateLabOrderAction::class);
    $order = $createAction->execute($this->encounter, [$this->fbpTest->id], 'Routine');
    $item = $order->items->first();

    $enterResultsAction = app(RecordLabResultsAction::class);

    // Normal values
    $updatedItem = $enterResultsAction->execute($item, [
        'Hemoglobin (Hb)' => '14.5',
        'Platelets' => '300',
    ], 'Normal hematological profile');

    expect($updatedItem->status)->toBe('Completed')
        ->and($updatedItem->has_critical_value)->toBeFalse()
        ->and($updatedItem->critical_value_alerted_at)->toBeNull()
        ->and($updatedItem->labOrder->status)->toBe('Completed');
});

test('duplicate active lab investigations in the same encounter are blocked', function () {
    $this->actingAs($this->user);

    $createAction = app(CreateLabOrderAction::class);
    // First order succeeds
    $createAction->execute($this->encounter, [$this->fbpTest->id], 'Routine');

    // Second order for the same test must throw InvalidArgumentException
    expect(fn () => $createAction->execute($this->encounter, [$this->fbpTest->id], 'STAT'))
        ->toThrow(InvalidArgumentException::class);
});

test('laboratory workspace renders correctly with queues and metrics', function () {
    $this->actingAs($this->user);

    $createAction = app(CreateLabOrderAction::class);
    $createAction->execute($this->encounter, [$this->fbpTest->id], 'Routine');

    $response = $this->get(route('laboratory.workspace'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workspace/LaboratoryWorkspace')
            ->has('labTests')
            ->has('pendingSamples')
            ->has('testingWorklist')
            ->has('completedResults')
            ->has('metrics')
        );
});

test('phlebotomist can collect specimen and assign accession barcode via action', function () {
    $this->actingAs($this->user);

    $createAction = app(CreateLabOrderAction::class);
    $order = $createAction->execute($this->encounter, [$this->fbpTest->id], 'STAT');
    $item = $order->items->first();

    $collectAction = app(CollectSpecimenAction::class);
    $collectedItem = $collectAction->execute($item, 'ACC-2026-TEST99', 'Specimen collected without hemolysis');

    expect($collectedItem->status)->toBe('Sample Collected')
        ->and($collectedItem->specimen_barcode)->toBe('ACC-2026-TEST99')
        ->and($collectedItem->labOrder->status)->toBe('Sample Collected')
        ->and($collectedItem->labOrder->collected_at)->not->toBeNull();
});

test('pathologist can electronically verify diagnostic results', function () {
    $this->actingAs($this->user);

    $createAction = app(CreateLabOrderAction::class);
    $order = $createAction->execute($this->encounter, [$this->fbpTest->id], 'STAT');
    $item = $order->items->first();

    $recordAction = app(RecordLabResultsAction::class);
    $recordedItem = $recordAction->execute($item, [
        'Hemoglobin (Hb)' => '13.8',
        'Platelets' => '240',
    ]);

    $verifyAction = app(VerifyLabResultsAction::class);
    $verifiedItem = $verifyAction->execute($recordedItem, 'Morphology normal, verified');

    expect($verifiedItem->status)->toBe('Completed')
        ->and($verifiedItem->verified_by_id)->toBe($this->user->id)
        ->and($verifiedItem->technician_remarks)->toContain('Pathologist Sign-off');
});
