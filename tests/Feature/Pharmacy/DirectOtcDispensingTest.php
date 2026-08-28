<?php

namespace Tests\Feature\Pharmacy;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Actions\DispenseDirectOtcAction;
use App\Domains\Pharmacy\Exceptions\PharmacyException;
use App\Domains\Pharmacy\Models\DispenseEvent;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectOtcDispensingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Facility $facility;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'AfyaNova Clinic',
            'slug' => 'afyanova-clinic',
            'domain' => 'afyanova.test',
            'status' => 'active',
        ]);

        setTestTenantContext($this->tenant->id);

        $this->facility = Facility::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Hospital',
            'code' => 'FAC-001',
            'facility_type' => 'Hospital',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $role = \App\Domains\Identity\Models\Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pharmacist',
            'slug' => 'pharmacist',
        ]);
        $perm = \App\Domains\Identity\Models\Permission::firstOrCreate(
            ['slug' => 'pharmacy.dispense.execute'],
            [
                'name' => 'Dispense Medication',
                'domain' => 'Pharmacy',
            ]
        );
        $role->permissions()->attach($perm->id);
        
        \App\Domains\Identity\Models\RoleAssignment::create([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'facility_id' => null,
        ]);

        app(\App\Domains\Identity\Services\AuthorizationService::class)->clearUserCache($this->user);

        $this->actingAs($this->user);
    }

    public function test_direct_otc_dispensing_deducts_stock_via_fefo_and_creates_invoice(): void
    {
        $patient = Patient::create([
            'tenant_id' => $this->tenant->id,
            'registered_at_facility_id' => $this->facility->id,
            'primary_mrn' => 'MRN-OTC-001',
            'first_name' => 'John',
            'last_name' => 'Mrema',
            'dob' => '1990-01-01',
            'gender' => 'Male',
            'phone' => '255712345678',
        ]);

        $medication = MedicationFormulary::create([
            'tenant_id' => $this->tenant->id,
            'generic_name' => 'Paracetamol',
            'brand_name' => 'Panadol Extra',
            'strength' => '500mg',
            'form' => 'Tablet',
            'route' => 'PO',
            'category' => 'Pharmaceutical',
            'is_active' => true,
        ]);

        // Create 2 batches with different expiry dates (FEFO)
        $batchEarlier = InventoryBatch::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'medication_id' => $medication->id,
            'batch_number' => 'BATCH-EXP-EARLY',
            'expiry_date' => now()->addMonths(2),
            'initial_quantity' => 10,
            'current_quantity' => 10,
            'unit_cost' => 50,
            'unit_selling_price' => 200,
            'status' => 'Active',
        ]);

        $batchLater = InventoryBatch::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'medication_id' => $medication->id,
            'batch_number' => 'BATCH-EXP-LATER',
            'expiry_date' => now()->addMonths(12),
            'initial_quantity' => 20,
            'current_quantity' => 20,
            'unit_cost' => 50,
            'unit_selling_price' => 200,
            'status' => 'Active',
        ]);

        $ticket = QueueTicket::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $patient->id,
            'ticket_number' => 'PHM-001',
            'current_service_point' => 'Pharmacy',
            'status' => QueueTicketStatus::Waiting,
            'joined_queue_at' => now(),
        ]);

        $action = app(DispenseDirectOtcAction::class);

        $result = $action->execute([
            'patient_id' => $patient->id,
            'ticket_id' => $ticket->id,
            'notes' => 'Patient purchased for fever and headache',
            'items' => [
                [
                    'medication_id' => $medication->id,
                    'quantity' => 15,
                    'unit_price' => 200,
                    'instructions' => 'Take 2 tablets TID',
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(3000.0, $result['total_amount']);

        // Check FEFO deduction: batchEarlier should be depleted (0 remaining), batchLater should have 15 remaining
        $batchEarlier->refresh();
        $batchLater->refresh();
        $this->assertEquals(0, $batchEarlier->current_quantity);
        $this->assertEquals('Depleted', $batchEarlier->status);
        $this->assertEquals(15, $batchLater->current_quantity);

        // Check stock movement records
        $movements = StockMovement::where('medication_id', $medication->id)->get();
        $this->assertCount(2, $movements);

        // Check invoice creation
        $this->assertNotNull($result['invoice']);
        $invoice = Invoice::find($result['invoice']->id);
        $this->assertEquals(3000.0, (float) $invoice->total_amount);
        $this->assertCount(1, $invoice->lineItems);

        // Check queue ticket completion
        $ticket->refresh();
        $this->assertEquals(QueueTicketStatus::Completed, $ticket->status);
    }

    public function test_direct_otc_dispensing_fails_when_insufficient_stock(): void
    {
        $medication = MedicationFormulary::create([
            'tenant_id' => $this->tenant->id,
            'generic_name' => 'Amoxicillin',
            'strength' => '500mg',
            'form' => 'Capsule',
            'route' => 'PO',
            'category' => 'Pharmaceutical',
            'is_active' => true,
        ]);

        InventoryBatch::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'medication_id' => $medication->id,
            'batch_number' => 'AMX-001',
            'expiry_date' => now()->addMonths(6),
            'initial_quantity' => 5,
            'current_quantity' => 5,
            'unit_cost' => 100,
            'unit_selling_price' => 500,
            'status' => 'Active',
        ]);

        $this->expectException(PharmacyException::class);

        $action = app(DispenseDirectOtcAction::class);
        $action->execute([
            'items' => [
                [
                    'medication_id' => $medication->id,
                    'quantity' => 10,
                    'unit_price' => 500,
                ],
            ],
        ]);
    }

    public function test_controller_endpoint_handles_otc_dispense(): void
    {
        $medication = MedicationFormulary::create([
            'tenant_id' => $this->tenant->id,
            'generic_name' => 'Ibuprofen',
            'strength' => '400mg',
            'form' => 'Tablet',
            'route' => 'PO',
            'category' => 'Pharmaceutical',
            'is_active' => true,
        ]);

        InventoryBatch::create([
            'tenant_id' => $this->tenant->id,
            'facility_id' => $this->facility->id,
            'medication_id' => $medication->id,
            'batch_number' => 'IBU-001',
            'expiry_date' => now()->addMonths(6),
            'initial_quantity' => 20,
            'current_quantity' => 20,
            'unit_cost' => 100,
            'unit_selling_price' => 300,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->user)->post(route('pharmacy.dispense-direct-otc'), [
            'notes' => 'Direct OTC sale at counter',
            'items' => [
                [
                    'medication_id' => $medication->id,
                    'quantity' => 5,
                    'unit_price' => 300,
                    'instructions' => 'Take 1 tablet after meals',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('invoices', [
            'total_amount' => 1500,
        ]);
    }
}
