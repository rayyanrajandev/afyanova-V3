<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('patient_id');
            $table->uuid('encounter_id')->nullable();
            $table->uuid('admission_id')->nullable();
            $table->uuid('reconciled_by');

            $table->string('stage', 30); // Admission, Transfer, Discharge
            $table->string('medication_name', 255);
            $table->string('dosage', 100)->nullable();
            $table->string('frequency', 100)->nullable();
            $table->string('route', 50)->nullable();
            $table->string('action_taken', 50); // Continue, Discontinue, Substitute, ModifyDose, Hold
            $table->text('clinical_rationale')->nullable();
            $table->string('substitute_medication_name', 255)->nullable();
            $table->string('new_dosage_instructions', 255)->nullable();

            $table->timestamp('reconciled_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('reconciled_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('set null');
            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('set null');

            $table->index(['tenant_id', 'patient_id', 'stage']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE medication_reconciliations ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE medication_reconciliations FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON medication_reconciliations
                FOR ALL
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON medication_reconciliations');
        }
        Schema::dropIfExists('medication_reconciliations');
    }
};
