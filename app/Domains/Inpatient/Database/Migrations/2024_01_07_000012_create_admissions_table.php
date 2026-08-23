<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id')->nullable();
            $table->uuid('encounter_id')->nullable();
            $table->uuid('patient_id');
            $table->uuid('admitting_doctor_id');
            $table->uuid('ward_id');
            $table->uuid('bed_id');

            $table->string('admission_number')->unique(); // e.g. ADM-2026-0001
            $table->text('admission_reason');
            $table->string('provisional_diagnosis')->nullable();
            $table->timestamp('admitted_at')->useCurrent();
            $table->timestamp('discharged_at')->nullable();

            $table->string('discharge_disposition')->nullable(); // Home, Transferred_Facility, Deceased, Against_Medical_Advice
            $table->text('discharge_summary')->nullable();
            $table->uuid('discharged_by')->nullable();

            $table->string('status')->default('Admitted'); // Admitted, Discharged, Transferred
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('admitting_doctor_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('ward_id')->references('id')->on('wards')->onDelete('restrict');
            $table->foreign('bed_id')->references('id')->on('beds')->onDelete('restrict');
            $table->foreign('discharged_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['tenant_id', 'status', 'admitted_at'], 'idx_inpatient_census');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE admissions ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE admissions FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON admissions
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON admissions');
        }
        Schema::dropIfExists('admissions');
    }
};
