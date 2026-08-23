<?php

use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Actions\AssignUserRoleAction;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Procedure\Actions\BookSurgicalCaseAction;
use App\Domains\Procedure\Actions\CompleteWhoChecklistAction;
use App\Domains\Procedure\Actions\CreateProcedureOrderAction;
use App\Domains\Procedure\Actions\RecordPacuTelemetryAction;
use App\Domains\Procedure\Actions\RecordProcedureExecutionAction;
use App\Domains\Procedure\Models\OperatingSuite;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Procedure\Models\ProcedureOrder;
use App\Domains\Procedure\Models\SurgicalBooking;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Kigamboni Health Hub',
        'slug' => 'kigamboni',
        'domain' => 'kigamboni',
        'status' => 'Active',
    ]);

    app(TenantContext::class)->setTenantId($this->tenant->id);

    $this->facility = Facility::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Kigamboni Main Facility',
        'code' => 'KIG-01',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'dr.rajani@afyanova.local',
        'first_name' => 'Rajani',
        'last_name' => 'Massawe',
    ]);

    $doctorRole = Role::create(['tenant_id' => $this->tenant->id, 'slug' => 'doctor', 'name' => 'Doctor']);
    $orderPermission = Permission::firstOrCreate(
        ['slug' => 'procedure.order.create'],
        ['name' => 'Order Procedures', 'domain' => 'Procedure']
    );
    $doctorRole->permissions()->attach($orderPermission->id);
    app(AssignUserRoleAction::class)->execute($this->user->id, $doctorRole->id);

    $this->patient = Patient::create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Asha',
        'last_name' => 'Juma',
        'primary_mrn' => 'MRN-2026-0001',
        'gender' => 'Female',
        'dob' => '1990-05-12',
    ]);

    $this->encounter = Encounter::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'patient_id' => $this->patient->id,
        'provider_id' => $this->user->id,
        'status' => 'InProgress',
        'encounter_type' => 'Outpatient',
    ]);

    $this->procedureCatalog = ProcedureCatalog::create([
        'tenant_id' => $this->tenant->id,
        'procedure_code' => 'PROC-DRS-001',
        'name' => 'Wound Debridement & Sterile Dressing',
        'category' => 'Dressing',
        'tier_level' => 'Tier1_Minor',
        'standard_price' => 15000.00,
        'is_active' => true,
    ]);

    $this->surgicalCatalog = ProcedureCatalog::create([
        'tenant_id' => $this->tenant->id,
        'procedure_code' => 'SURG-CS-001',
        'name' => 'Emergency Caesarean Section',
        'category' => 'OBGYN',
        'tier_level' => 'Tier2_MajorTheatre',
        'standard_price' => 450000.00,
        'is_active' => true,
    ]);

    $this->suite = OperatingSuite::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'name' => 'Major Operating Suite 1',
        'suite_code' => 'OT-SUITE-1',
        'suite_type' => 'Major',
        'status' => 'Available',
    ]);

    $this->ward = Ward::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'name' => 'Surgical Recovery Ward',
        'code' => 'SURG-01',
    ]);

    $this->medication = MedicationFormulary::create([
        'tenant_id' => $this->tenant->id,
        'generic_name' => 'Sterile Gauze Swabs 10x10cm',
        'brand_name' => 'Gauze Swabs',
        'form' => 'Consumable',
        'strength' => 'Pack of 5',
        'route' => 'Topical',
    ]);

    $this->batch = InventoryBatch::create([
        'tenant_id' => $this->tenant->id,
        'facility_id' => $this->facility->id,
        'medication_id' => $this->medication->id,
        'batch_number' => 'GAUZE-2026',
        'initial_quantity' => 100,
        'current_quantity' => 100,
        'unit_cost' => 1000,
        'unit_selling_price' => 1500,
        'expiry_date' => now()->addYear(),
        'status' => 'Active',
    ]);
});

test('create procedure order generates unique order number and auto-bills invoice', function () {
    $this->actingAs($this->user);

    $action = app(CreateProcedureOrderAction::class);
    $order = $action->execute($this->encounter, $this->procedureCatalog->id, 'Urgent', 'Infected traumatic wound');

    expect($order)->toBeInstanceOf(ProcedureOrder::class)
        ->and($order->order_number)->toStartWith('PR-'.date('Y'))
        ->and($order->priority)->toBe('Urgent')
        ->and($order->status)->toBe('Ordered');

    $this->assertDatabaseHas('procedure_orders', [
        'id' => $order->id,
        'procedure_catalog_id' => $this->procedureCatalog->id,
    ]);

    // Check Auto-billing
    $this->assertDatabaseHas('invoice_line_items', [
        'category' => 'Procedure',
        'unit_price' => 15000.00,
    ]);
});

test('record procedure execution logs dressing notes and atomically decrements inventory stock', function () {
    $this->actingAs($this->user);

    $order = app(CreateProcedureOrderAction::class)->execute($this->encounter, $this->procedureCatalog->id);

    $action = app(RecordProcedureExecutionAction::class);
    $execution = $action->execute($order, [
        'execution_setting' => 'DressingRoom',
        'anesthesia_type' => 'Local',
        'wound_condition' => 'Clean',
        'findings_and_technique' => 'Cleaned wound with normal saline, applied sterile gauze',
        'follow_up_date' => now()->addDays(3)->toDateString(),
    ], [
        [
            'item_name' => 'Sterile Gauze Swabs 10x10cm',
            'batch_id' => $this->batch->id,
            'quantity_used' => 2,
            'unit_price' => 1500,
        ],
    ]);

    expect($execution->wound_condition)->toBe('Clean')
        ->and($execution->consumables)->toHaveCount(1)
        ->and($order->fresh()->status)->toBe('Completed');

    // Batch should have decreased from 100 to 98
    expect($this->batch->fresh()->current_quantity)->toEqual(98);

    // Stock Movement ledger created
    $this->assertDatabaseHas('stock_movements', [
        'batch_id' => $this->batch->id,
        'movement_type' => 'Dispensed',
        'quantity_change' => -2,
    ]);
});

test('book surgical case schedules theatre and initializes who checklist', function () {
    $this->actingAs($this->user);

    $surgOrder = app(CreateProcedureOrderAction::class)->execute($this->encounter, $this->surgicalCatalog->id, 'Emergency');

    $action = app(BookSurgicalCaseAction::class);
    $booking = $action->execute($surgOrder, $this->suite->id, [
        'lead_surgeon_id' => $this->user->id,
        'scheduled_start' => now()->addHour(),
        'scheduled_end' => now()->addHours(3),
        'urgency' => 'Emergency',
    ]);

    expect($booking)->toBeInstanceOf(SurgicalBooking::class)
        ->and($booking->booking_number)->toStartWith('SURG-'.date('Y'))
        ->and($booking->whoChecklist)->not->toBeNull()
        ->and($surgOrder->fresh()->status)->toBe('InProgress');

    $this->assertDatabaseHas('who_surgical_checklists', [
        'surgical_booking_id' => $booking->id,
        'sponge_and_needle_count_correct' => true,
    ]);
});

test('complete who checklist signs off safety stages', function () {
    $this->actingAs($this->user);

    $surgOrder = app(CreateProcedureOrderAction::class)->execute($this->encounter, $this->surgicalCatalog->id, 'Emergency');
    $booking = app(BookSurgicalCaseAction::class)->execute($surgOrder, $this->suite->id, []);

    $checklist = $booking->whoChecklist;
    $action = app(CompleteWhoChecklistAction::class);

    $action->execute($checklist, 'time_out', [
        'sponge_and_needle_count_correct' => true,
        'specimens_labeled_correctly' => true,
    ]);

    expect($checklist->fresh()->time_out_completed_at)->not->toBeNull()
        ->and($checklist->fresh()->time_out_verified_by)->toBe($this->user->id);
});

test('record pacu telemetry calculates aldrete score and confirms ward readiness', function () {
    $this->actingAs($this->user);

    $surgOrder = app(CreateProcedureOrderAction::class)->execute($this->encounter, $this->surgicalCatalog->id, 'Emergency');
    $booking = app(BookSurgicalCaseAction::class)->execute($surgOrder, $this->suite->id, []);

    $action = app(RecordPacuTelemetryAction::class);
    $record = $action->execute($booking, [
        'consciousness_score' => 2,
        'activity_score' => 2,
        'respiration_score' => 2,
        'circulation_score' => 2,
        'oxygen_saturation_score' => 2,
    ], $this->ward->id, 'Patient fully recovered.');

    expect($record->total_aldrete_score)->toBe(10)
        ->and($record->discharge_ready)->toBeTrue()
        ->and($booking->fresh()->status)->toBe('Completed');
});

test('procedure workspace renders correctly with treatment queues and metrics', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('procedures.workspace'));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Workspace/ProcedureWorkspace')
            ->has('procedureCatalogs')
            ->has('dressingQueue')
            ->has('surgicalBookings')
            ->has('operatingSuites')
            ->has('metrics')
        );
});

test('doctor can order procedure from consultation desk and verify dual-pathway routing to dressing desk', function () {
    $this->actingAs($this->user);

    // 1. Doctor orders procedure via HTTP endpoint during consultation
    $response = $this->post(route('procedures.orders.store'), [
        'encounter_id' => $this->encounter->id,
        'procedure_catalog_id' => $this->procedureCatalog->id,
        'priority' => 'Urgent',
        'clinical_indication' => 'Dirty forearm laceration requiring debridement and sterile dressing',
    ]);

    $response->assertRedirect();

    $order = ProcedureOrder::where('encounter_id', $this->encounter->id)->first();
    expect($order)->not->toBeNull()
        ->and($order->priority)->toBe('Urgent')
        ->and($order->status)->toBe('Ordered');

    // 2. Encounter in clinical workspace now loads this procedure order
    $clinicalResponse = $this->get(route('encounters.workspace', $this->encounter->id));
    $clinicalResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Workspace/ClinicalWorkspace')
            ->has('procedureCatalogs')
            ->where('encounter.procedure_orders.0.order_number', $order->order_number)
        );

    // 3. Nurse at Procedure Desk sees the order in dressingQueue
    $procedureDeskResponse = $this->get(route('procedures.workspace'));
    $procedureDeskResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('dressingQueue.0.order_number', $order->order_number)
        );
});
