<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_problems', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('patient_id');
            $table->uuid('recorded_by');
            $table->uuid('encounter_id')->nullable();

            $table->string('icd10_code', 20);
            $table->string('problem_name', 255);
            $table->string('status', 30)->default('Active'); // Active, Resolved, Remission, Inactive
            $table->string('clinical_status', 30)->default('Confirmed'); // Suspected, Confirmed, Refuted
            $table->string('severity', 20)->default('Moderate'); // Mild, Moderate, Severe
            $table->date('onset_date')->nullable();
            $table->date('resolved_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('set null');

            $table->index(['tenant_id', 'patient_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE patient_problems ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE patient_problems FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON patient_problems
                FOR ALL
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON patient_problems');
        }
        Schema::dropIfExists('patient_problems');
    }
};
