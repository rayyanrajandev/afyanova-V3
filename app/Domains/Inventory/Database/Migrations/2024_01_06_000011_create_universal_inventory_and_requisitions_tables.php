<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Units of Measure (UOM)
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name'); // Piece, Box, Carton, Drum, Litre, Vial, Ampoule, Roll, Cylinder, Bale
            $table->string('symbol', 15); // pc, bx, ctn, drm, L, vial, amp, roll, cyl, bale
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Universal Hospital Item Master
        Schema::create('item_masters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('item_code')->unique(); // MSD or Hospital SKU e.g. MSD-SURG-001
            $table->string('name'); // e.g. IV Cannula G20 with Injection Port
            $table->string('generic_name')->nullable();
            $table->string('category')->default('Surgical_Consumable'); // Pharmaceutical, Surgical_Consumable, Lab_Reagent, IPC_Chemical, Linen_Apparel, Stationery_MTUHA, Medical_Gas, Nutrition_Food, Fixed_Asset
            $table->string('sub_category')->nullable();
            $table->foreignUuid('base_uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->foreignUuid('purchasing_uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->integer('conversion_ratio')->default(1); // e.g. 1 Box = 100 Pieces
            $table->integer('reorder_level')->default(20);
            $table->integer('safety_stock')->default(10);
            $table->decimal('unit_cost_price', 12, 2)->default(0.00);
            $table->decimal('unit_selling_price', 12, 2)->default(0.00);
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_cold_chain')->default(false);
            $table->boolean('is_dda_narcotic')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('medication_id')->nullable()->constrained('medication_formularies')->nullOnDelete();
            $table->timestamps();
        });

        // 3. Departmental Store Requisitions (Store Indents)
        Schema::create('department_requisitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->string('requisition_number')->unique(); // REQ-2026-XXXX
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUuid('source_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignUuid('destination_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('requisition_type')->default('Routine_Weekly'); // Routine_Weekly, Emergency_Stockout
            $table->string('status')->default('Draft'); // Draft, Submitted, Approved, Dispatched_In_Transit, Received_Confirmed, Disputed, Rejected
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Department Requisition Items
        Schema::create('department_requisition_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('department_requisition_id')->constrained('department_requisitions')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('item_masters')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->integer('quantity_requested');
            $table->integer('quantity_approved')->default(0);
            $table->integer('quantity_dispatched')->default(0);
            $table->integer('quantity_received')->default(0);
            $table->string('discrepancy_reason')->nullable();
            $table->timestamps();
        });

        // 5. Dangerous Drugs Register (DDA Narcotics Logs)
        Schema::create('dda_register_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('item_masters')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignUuid('prescriber_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('administering_nurse_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('witness_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('dose_administered', 10, 2);
            $table->decimal('dose_wasted_discarded', 10, 2)->default(0.00);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('indication')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. Medical Gas & Oxygen Cylinders Fleet
        Schema::create('medical_gas_cylinders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->string('cylinder_serial_number')->unique(); // OXY-CYL-098
            $table->string('gas_type')->default('Oxygen'); // Oxygen, Nitrous_Oxide, Carbon_Dioxide, Medical_Air
            $table->string('cylinder_size')->default('Size_J'); // Size_J, Size_G, Size_E
            $table->integer('volume_liters')->default(8500);
            $table->foreignUuid('current_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('status')->default('Full_In_Store'); // Full_In_Store, In_Use_Ward, Empty_Return_Bay, Dispatched_Refill
            $table->string('assigned_ward_bed')->nullable();
            $table->timestamp('last_refilled_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE units_of_measure ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE units_of_measure FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON units_of_measure
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE item_masters ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE item_masters FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON item_masters
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE department_requisitions ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE department_requisitions FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON department_requisitions
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE department_requisition_items ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE department_requisition_items FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON department_requisition_items
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE dda_register_logs ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE dda_register_logs FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON dda_register_logs
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE medical_gas_cylinders ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE medical_gas_cylinders FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON medical_gas_cylinders
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON medical_gas_cylinders');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON dda_register_logs');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON department_requisition_items');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON department_requisitions');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON item_masters');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON units_of_measure');
        }
        Schema::dropIfExists('medical_gas_cylinders');
        Schema::dropIfExists('dda_register_logs');
        Schema::dropIfExists('department_requisition_items');
        Schema::dropIfExists('department_requisitions');
        Schema::dropIfExists('item_masters');
        Schema::dropIfExists('units_of_measure');
    }
};
