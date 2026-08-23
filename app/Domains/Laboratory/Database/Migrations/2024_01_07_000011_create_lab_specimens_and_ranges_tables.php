<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Lab Specimens (physical sample tracking & barcodes)
        Schema::create('lab_specimens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('lab_order_id');
            $table->uuid('patient_id');
            $table->uuid('collected_by')->nullable();

            $table->string('accession_number', 50); // e.g. LAB-2026-000123
            $table->string('sample_type', 50); // Blood, Urine, Stool, Sputum, CSF, Tissue
            $table->string('container_type', 50)->nullable(); // EDTA, Serum Gel, Sodium Citrate, Sterile Cup
            $table->string('collection_site', 100)->nullable();
            $table->string('status', 30)->default('Collected'); // Collected, InLab, Processing, Rejected, Discarded
            $table->text('rejection_reason')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('received_in_lab_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('lab_order_id')->references('id')->on('lab_orders')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('collected_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['tenant_id', 'accession_number']);
        });

        // 2. Stratified Reference Ranges for Lab Tests
        Schema::create('lab_test_ranges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('lab_test_id');

            $table->string('parameter_name', 100)->nullable();
            $table->string('gender', 20)->default('All'); // Male, Female, All
            $table->integer('age_min_days')->default(0);
            $table->integer('age_max_days')->default(36500); // 100 years
            $table->decimal('normal_min', 10, 3)->nullable();
            $table->decimal('normal_max', 10, 3)->nullable();
            $table->decimal('critical_low', 10, 3)->nullable();
            $table->decimal('critical_high', 10, 3)->nullable();
            $table->string('unit', 30)->nullable(); // e.g. g/dL, mmol/L, 10^9/L
            $table->text('textual_normal_range')->nullable(); // For qualitative tests (e.g. Negative)

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('lab_test_id')->references('id')->on('lab_tests')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE lab_specimens ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE lab_specimens FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON lab_specimens
                FOR ALL
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            ");

            DB::statement('ALTER TABLE lab_test_ranges ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE lab_test_ranges FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON lab_test_ranges
                FOR ALL
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON lab_test_ranges');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON lab_specimens');
        }
        Schema::dropIfExists('lab_test_ranges');
        Schema::dropIfExists('lab_specimens');
    }
};
