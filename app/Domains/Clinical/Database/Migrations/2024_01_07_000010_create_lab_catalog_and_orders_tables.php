<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Diagnostic Test Catalog
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('test_code')->unique();
            $table->string('name');
            $table->string('category')->index(); // Hematology, Parasitology, Clinical Chemistry, Microbiology, Urinalysis
            $table->string('specimen_type'); // Whole Blood (EDTA), Serum, Clean Catch Urine, etc.
            $table->integer('turnaround_time_minutes')->default(30);
            $table->decimal('price', 12, 2)->default(0.00);
            $table->json('parameters')->nullable(); // Schema of parameters with unit, ref min/max, panic low/high
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Clinical Lab Orders
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('order_number')->unique();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('ordering_provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('Routine'); // STAT, Urgent, Routine
            $table->text('clinical_notes')->nullable();
            $table->string('status')->default('Ordered'); // Ordered, Sample Collected, In Progress, Completed, Cancelled
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 3. Lab Order Items & Result Findings
        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->foreignUuid('lab_test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->decimal('price', 12, 2)->default(0.00);
            $table->string('status')->default('Pending'); // Pending, Sample Collected, Testing, Completed
            $table->string('specimen_barcode')->nullable();
            $table->json('results')->nullable(); // Parameter values + technician findings
            $table->text('technician_remarks')->nullable();
            $table->boolean('has_critical_value')->default(false);
            $table->timestamp('critical_value_alerted_at')->nullable();
            $table->foreignUuid('performed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('verified_by_id')->nullable()->constrained('users')->nullOnDelete();

            // Immutability Amendment Pattern
            $table->boolean('is_amendment')->default(false);
            $table->uuid('amended_result_item_id')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->boolean('is_deprecated')->default(false);

            $table->timestamps();
        });

        // Self-referencing FK added after the table exists — a chained
        // ->primary() compiles to an ALTER TABLE that Postgres runs AFTER
        // any foreign keys declared inside the same Schema::create() block,
        // so a self-reference declared there fails because its own primary
        // key doesn't exist yet at that point.
        Schema::table('lab_order_items', function (Blueprint $table) {
            $table->foreign('amended_result_item_id')->references('id')->on('lab_order_items')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE lab_tests ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE lab_tests FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON lab_tests
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE lab_orders ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE lab_orders FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON lab_orders
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE lab_order_items ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE lab_order_items FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON lab_order_items
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON lab_order_items');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON lab_orders');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON lab_tests');
        }
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_tests');
    }
};
