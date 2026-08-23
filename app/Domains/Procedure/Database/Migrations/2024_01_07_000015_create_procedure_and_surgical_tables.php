<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Master Procedure Catalog
        Schema::create('procedure_catalogs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('procedure_code')->unique(); // e.g. PROC-DRS-001, PROC-IND-001, SURG-CS-001
            $table->string('name');
            $table->string('category')->default('Dressing'); // Dressing, MinorSurgery, Injection, MajorSurgery, Orthopedic, OBGYN
            $table->string('tier_level')->default('Tier1_Minor'); // Tier1_Minor, Tier2_MajorTheatre
            $table->integer('default_duration_minutes')->default(20);
            $table->decimal('standard_price', 12, 2)->default(0.00);
            $table->boolean('requires_consent')->default(false);
            $table->boolean('requires_anesthesia')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Procedure Orders (Placed by Clinicians)
        Schema::create('procedure_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('order_number')->unique(); // e.g. PR-2026-0001
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('ordering_provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('procedure_catalog_id')->constrained('procedure_catalogs')->cascadeOnDelete();
            $table->string('priority')->default('Routine'); // Routine, Urgent, Emergency
            $table->text('clinical_indication')->nullable();
            $table->string('status')->default('Ordered'); // Ordered, InProgress, Completed, Cancelled
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 3. Procedure Executions (Dressing Room or Theatre notes)
        Schema::create('procedure_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('procedure_order_id')->constrained('procedure_orders')->cascadeOnDelete();
            $table->foreignUuid('performed_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('assistant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('execution_setting')->default('DressingRoom'); // DressingRoom, MinorTheatre, MajorTheatre
            $table->string('anesthesia_type')->default('Local'); // None, Local, Spinal, General, Sedation
            $table->string('wound_condition')->nullable(); // Clean, Contaminated, Purulent, Granulating, Epithelializing
            $table->text('findings_and_technique');
            $table->text('post_procedure_instructions')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();
        });

        // 4. Consumables Used during Procedure
        Schema::create('procedure_consumables_used', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('procedure_execution_id')->constrained('procedure_executions')->cascadeOnDelete();
            $table->string('item_name');
            $table->foreignUuid('medication_id')->nullable()->constrained('medication_formularies')->nullOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->decimal('quantity_used', 8, 2)->default(1.00);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->boolean('is_billed_to_patient')->default(true);
            $table->timestamps();
        });

        // 5. Operating Suites / Theatres (Tier 2)
        Schema::create('operating_suites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->string('name'); // Major Theatre 1, Minor Theatre 2, Maternity OT
            $table->string('suite_code')->unique();
            $table->string('suite_type')->default('Major'); // Major, Minor, Obstetric, Endoscopy
            $table->string('status')->default('Available'); // Available, Occupied, Cleaning, Maintenance
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 6. Surgical Bookings & Schedules (Tier 2)
        Schema::create('surgical_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('booking_number')->unique(); // e.g. SURG-2026-0001
            $table->foreignUuid('procedure_order_id')->constrained('procedure_orders')->cascadeOnDelete();
            $table->foreignUuid('operating_suite_id')->constrained('operating_suites')->cascadeOnDelete();
            $table->foreignUuid('lead_surgeon_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('anesthetist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('scrub_nurse_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->string('urgency')->default('Elective'); // Elective, Urgent, Emergency
            $table->string('status')->default('Scheduled'); // Scheduled, InTheatre, PACU, Completed, Cancelled
            $table->timestamps();
        });

        // 7. WHO Surgical Safety Checklists (Tier 2)
        Schema::create('who_surgical_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('surgical_booking_id')->constrained('surgical_bookings')->cascadeOnDelete();
            $table->timestamp('sign_in_completed_at')->nullable();
            $table->foreignUuid('sign_in_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('time_out_completed_at')->nullable();
            $table->foreignUuid('time_out_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sign_out_completed_at')->nullable();
            $table->foreignUuid('sign_out_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('sponge_and_needle_count_correct')->default(true);
            $table->boolean('specimens_labeled_correctly')->default(true);
            $table->json('checklist_data')->nullable();
            $table->timestamps();
        });

        // 8. PACU Post-Anesthesia Aldrete Recovery Telemetry (Tier 2)
        Schema::create('pacu_recovery_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('surgical_booking_id')->constrained('surgical_bookings')->cascadeOnDelete();
            $table->foreignUuid('recorded_by_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->integer('consciousness_score')->default(2); // 0 to 2
            $table->integer('activity_score')->default(2);      // 0 to 2
            $table->integer('respiration_score')->default(2);   // 0 to 2
            $table->integer('circulation_score')->default(2);   // 0 to 2
            $table->integer('oxygen_saturation_score')->default(2); // 0 to 2
            $table->integer('total_aldrete_score')->default(10); // 0 to 10
            $table->boolean('discharge_ready')->default(true);
            $table->foreignUuid('destination_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE procedure_catalogs ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE procedure_catalogs FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON procedure_catalogs
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE procedure_orders ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE procedure_orders FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON procedure_orders
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE procedure_executions ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE procedure_executions FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON procedure_executions
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE procedure_consumables_used ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE procedure_consumables_used FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON procedure_consumables_used
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE operating_suites ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE operating_suites FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON operating_suites
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE surgical_bookings ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE surgical_bookings FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON surgical_bookings
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE who_surgical_checklists ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE who_surgical_checklists FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON who_surgical_checklists
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pacu_recovery_records ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pacu_recovery_records FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON pacu_recovery_records
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON pacu_recovery_records');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON who_surgical_checklists');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON surgical_bookings');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON operating_suites');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON procedure_consumables_used');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON procedure_executions');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON procedure_orders');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON procedure_catalogs');
        }
        Schema::dropIfExists('pacu_recovery_records');
        Schema::dropIfExists('who_surgical_checklists');
        Schema::dropIfExists('surgical_bookings');
        Schema::dropIfExists('operating_suites');
        Schema::dropIfExists('procedure_consumables_used');
        Schema::dropIfExists('procedure_executions');
        Schema::dropIfExists('procedure_orders');
        Schema::dropIfExists('procedure_catalogs');
    }
};
