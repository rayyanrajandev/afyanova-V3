<?php

use App\Domains\Billing\Models\ChargeMasterItem;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Pharmacy\Actions\DispenseMedicationAction;
use App\Domains\Pharmacy\Actions\PrescribeMedicationAction;
use App\Domains\Pharmacy\Actions\ReceiveStockBatchAction;
use App\Domains\Pharmacy\Actions\VerifyPrescriptionAction;
use App\Domains\Pharmacy\Exceptions\PharmacyException;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;

test('pharmacist can receive a stock batch with ledger logging', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $medication = MedicationFormulary::create([
        'generic_name' => 'Ciprofloxacin',
        'brand_name' => 'Cipro',
        'form' => 'Tablet',
        'strength' => '500mg',
        'route' => 'Oral',
        'is_active' => true,
    ]);

    $receiveAction = app(ReceiveStockBatchAction::class);
    $batch = $receiveAction->execute([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'medication_id' => $medication->id,
        'batch_number' => 'CIP-2026-01',
        'expiry_date' => now()->addYear()->toDateString(),
        'quantity' => 200,
        'unit_cost' => 150.00,
        'unit_selling_price' => 300.00,
        'supplier_name' => 'MSD Tanzania',
        'performed_by' => $env['user']->id,
    ]);

    expect($batch)->toBeInstanceOf(InventoryBatch::class)
        ->and($batch->current_quantity)->toBe(200)
        ->and($batch->initial_quantity)->toBe(200)
        ->and($batch->status)->toBe('Active');

    // Assert StockMovement ledger was created
    $movement = StockMovement::where('batch_id', $batch->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->movement_type)->toBe('Received')
        ->and($movement->quantity_change)->toBe(200)
        ->and($movement->quantity_after)->toBe(200);

    // Refresh medication stock
    $medication->refresh();
    expect($medication->total_stock_on_hand)->toBe(200);
});

test('fefo dispense engine deducts from earliest expiry batch first', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Baraka',
        'last_name' => 'Massawe',
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
        'generic_name' => 'Artemether/Lumefantrine',
        'brand_name' => 'Coartem',
        'form' => 'Tablet',
        'strength' => '20/120mg',
        'route' => 'Oral',
        'charge_code' => 'PHARM-ALU-20-120',
        'is_active' => true,
    ]);
    ChargeMasterItem::create([
        'tenant_id' => $env['tenant']->id,
        'code' => 'PHARM-ALU-20-120',
        'name' => 'Artemether/Lumefantrine 20/120mg',
        'category' => 'Pharmacy',
        'unit_price' => 2500.00,
        'effective_from' => now()->subYear()->toDateString(),
    ]);

    // Batch A: Expires in 2 months (Earliest - FEFO Priority 1)
    $batchA = InventoryBatch::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'medication_id' => $medication->id,
        'batch_number' => 'BATCH-A-EXP-2M',
        'expiry_date' => now()->addMonths(2)->toDateString(),
        'initial_quantity' => 50,
        'current_quantity' => 50,
        'status' => 'Active',
    ]);

    // Batch B: Expires in 12 months (Later - FEFO Priority 2)
    $batchB = InventoryBatch::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'medication_id' => $medication->id,
        'batch_number' => 'BATCH-B-EXP-12M',
        'expiry_date' => now()->addMonths(12)->toDateString(),
        'initial_quantity' => 100,
        'current_quantity' => 100,
        'status' => 'Active',
    ]);

    // Prescribe 24 tablets
    $prescription = app(PrescribeMedicationAction::class)->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'medication_id' => $medication->id,
        'dosage' => '4 tablets',
        'frequency' => 'BID',
        'duration_days' => 3,
        'route' => 'Oral',
        'quantity' => 24,
    ]);

    // Verify prescription
    app(VerifyPrescriptionAction::class)->execute($prescription);

    // Dispense 24 tablets
    $dispenseAction = app(DispenseMedicationAction::class);
    $event = $dispenseAction->execute($prescription, 24);

    expect($event)->not->toBeNull();

    // Check that Batch A (earliest expiry) was deducted first
    $batchA->refresh();
    $batchB->refresh();

    expect($batchA->current_quantity)->toBe(26) // 50 - 24
        ->and($batchB->current_quantity)->toBe(100); // untouched

    // Check StockMovement created for Batch A
    $movement = StockMovement::where('batch_id', $batchA->id)->where('movement_type', 'Dispensed')->first();
    expect($movement)->not->toBeNull()
        ->and($movement->quantity_change)->toBe(-24);
});

test('fefo dispense splits deduction across multiple batches when needed', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Neema',
        'last_name' => 'Mbowe',
        'gender' => 'Female',
    ]);

    $encounter = app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'facility_id' => $env['facility']->id,
        'department_id' => null,
        'encounter_type' => 'OPD',
    ]);

    $medication = MedicationFormulary::create([
        'generic_name' => 'Metformin',
        'form' => 'Tablet',
        'strength' => '500mg',
        'route' => 'Oral',
        'charge_code' => 'PHARM-METFORMIN-500',
        'is_active' => true,
    ]);
    ChargeMasterItem::create([
        'tenant_id' => $env['tenant']->id,
        'code' => 'PHARM-METFORMIN-500',
        'name' => 'Metformin 500mg',
        'category' => 'Pharmacy',
        'unit_price' => 250.00,
        'effective_from' => now()->subYear()->toDateString(),
    ]);

    // Batch A: Only 10 units left, expires in 1 month
    $batchA = InventoryBatch::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'medication_id' => $medication->id,
        'batch_number' => 'MET-BATCH-1',
        'expiry_date' => now()->addMonths(1)->toDateString(),
        'initial_quantity' => 10,
        'current_quantity' => 10,
        'status' => 'Active',
    ]);

    // Batch B: 50 units, expires in 6 months
    $batchB = InventoryBatch::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'medication_id' => $medication->id,
        'batch_number' => 'MET-BATCH-2',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'initial_quantity' => 50,
        'current_quantity' => 50,
        'status' => 'Active',
    ]);

    // Prescribe 30 tablets
    $prescription = app(PrescribeMedicationAction::class)->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'medication_id' => $medication->id,
        'dosage' => '500mg',
        'frequency' => 'BID',
        'duration_days' => 15,
        'route' => 'Oral',
        'quantity' => 30,
    ]);

    app(VerifyPrescriptionAction::class)->execute($prescription);

    // Dispense 30 tablets (should consume 10 from Batch A and 20 from Batch B)
    $dispenseAction = app(DispenseMedicationAction::class);
    $event = $dispenseAction->execute($prescription, 30);

    $batchA->refresh();
    $batchB->refresh();

    expect($batchA->current_quantity)->toBe(0)
        ->and($batchA->status)->toBe('Depleted')
        ->and($batchB->current_quantity)->toBe(30); // 50 - 20
});

test('dispensing blocks with PharmacyException when insufficient stock is available', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $patient = app(RegisterPatientAction::class)->execute([
        'first_name' => 'Juma',
        'last_name' => 'Rashid',
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
        'generic_name' => 'Amoxicillin',
        'form' => 'Capsule',
        'strength' => '500mg',
        'route' => 'Oral',
        'charge_code' => 'PHARM-AMOXICILLIN-500',
        'is_active' => true,
    ]);
    ChargeMasterItem::create([
        'tenant_id' => $env['tenant']->id,
        'code' => 'PHARM-AMOXICILLIN-500',
        'name' => 'Amoxicillin 500mg',
        'category' => 'Pharmacy',
        'unit_price' => 300.00,
        'effective_from' => now()->subYear()->toDateString(),
    ]);

    // Batch with only 5 units in stock
    InventoryBatch::create([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'medication_id' => $medication->id,
        'batch_number' => 'AMX-LOW-01',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'initial_quantity' => 5,
        'current_quantity' => 5,
        'status' => 'Active',
    ]);

    // Prescribe 21 units
    $prescription = app(PrescribeMedicationAction::class)->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'medication_id' => $medication->id,
        'dosage' => '500mg',
        'frequency' => 'TDS',
        'duration_days' => 7,
        'route' => 'Oral',
        'quantity' => 21,
    ]);

    app(VerifyPrescriptionAction::class)->execute($prescription);

    // Attempting to dispense 21 units when only 5 units are in stock must throw PharmacyException
    $dispenseAction = app(DispenseMedicationAction::class);
    expect(fn () => $dispenseAction->execute($prescription, 21))
        ->toThrow(PharmacyException::class);
});
