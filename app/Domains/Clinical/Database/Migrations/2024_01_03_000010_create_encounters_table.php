<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('department_id')->nullable();
            $table->uuid('patient_id');
            $table->uuid('provider_id')->nullable(); // Attending physician/nurse
            $table->string('encounter_type', 50); // OPD, IPD, Emergency, Consultation
            $table->string('status', 50)->default('Triage'); // Triage, Doctor, Pharmacy, Closed
            $table->timestamp('start_time')->useCurrent();
            $table->timestamp('end_time')->nullable();
            $table->text('reason_for_visit')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('restrict');
        });

        // Apply RLS
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE encounters ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE encounters FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON encounters
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON encounters');
        }
        Schema::dropIfExists('encounters');
    }
};
