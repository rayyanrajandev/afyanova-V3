<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Radiology Orders (Imaging requests)
        Schema::create('radiology_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('encounter_id');
            $table->uuid('patient_id');
            $table->uuid('ordering_doctor_id');

            $table->string('order_number', 50); // e.g. RAD-2026-000123
            $table->string('modality', 30); // X-Ray, Ultrasound, CT Scan, MRI, Mammography, Echo
            $table->string('procedure_name', 255); // e.g. Chest X-Ray PA View, Abdominal Ultrasound
            $table->string('body_site', 100)->nullable(); // Chest, Abdomen, Pelvis, Brain, Spine
            $table->text('clinical_indication')->nullable();
            $table->string('priority', 20)->default('Routine'); // Routine, Urgent, STAT
            $table->string('status', 30)->default('Ordered'); // Ordered, Scheduled, Acquired, Reported, Cancelled
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('ordering_doctor_id')->references('id')->on('users')->onDelete('restrict');

            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'patient_id', 'status']);
        });

        // 2. Radiology Studies (Acquired DICOM / PACS metadata)
        Schema::create('radiology_studies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('radiology_order_id');
            $table->uuid('patient_id');
            $table->uuid('technician_id')->nullable();

            $table->string('study_instance_uid', 128)->nullable(); // DICOM UID
            $table->string('accession_number', 50)->nullable();
            $table->integer('series_count')->default(1);
            $table->integer('instance_count')->default(1);
            $table->string('pacs_storage_url', 500)->nullable();
            $table->text('technician_notes')->nullable();
            $table->timestamp('acquired_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('radiology_order_id')->references('id')->on('radiology_orders')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('technician_id')->references('id')->on('users')->onDelete('set null');
        });

        // 3. Radiology Reports (Radiologist diagnostic findings & electronic sign-off)
        Schema::create('radiology_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('radiology_order_id');
            $table->uuid('radiology_study_id')->nullable();
            $table->uuid('patient_id');
            $table->uuid('radiologist_id');

            $table->text('findings');
            $table->text('impression'); // Diagnostic conclusion
            $table->text('recommendations')->nullable();
            $table->boolean('is_critical_finding')->default(false);
            $table->timestamp('critical_notified_at')->nullable();
            $table->boolean('is_signed')->default(false);
            $table->timestamp('signed_at')->nullable();

            // Immutability Amendment pattern
            $table->boolean('is_amendment')->default(false);
            $table->uuid('amended_report_id')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->boolean('is_deprecated')->default(false);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('radiology_order_id')->references('id')->on('radiology_orders')->onDelete('restrict');
            $table->foreign('radiology_study_id')->references('id')->on('radiology_studies')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('radiologist_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::table('radiology_reports', function (Blueprint $table) {
            $table->foreign('amended_report_id')->references('id')->on('radiology_reports')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE radiology_orders ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE radiology_orders FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON radiology_orders
                FOR ALL
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            ");

            DB::statement('ALTER TABLE radiology_studies ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE radiology_studies FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON radiology_studies
                FOR ALL
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            ");

            DB::statement('ALTER TABLE radiology_reports ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE radiology_reports FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON radiology_reports
                FOR ALL
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON radiology_reports');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON radiology_studies');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON radiology_orders');
        }
        Schema::dropIfExists('radiology_reports');
        Schema::dropIfExists('radiology_studies');
        Schema::dropIfExists('radiology_orders');
    }
};
