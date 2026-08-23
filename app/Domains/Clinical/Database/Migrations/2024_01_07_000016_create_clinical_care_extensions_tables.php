<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Clinical & Surgical Informed Consents
        Schema::create('clinical_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('patient_id');
            $table->uuid('encounter_id')->nullable();
            $table->uuid('procedure_order_id')->nullable();
            $table->uuid('clinician_id');

            $table->string('consent_type', 50); // Surgical, Anesthesia, BloodTransfusion, InvasiveProcedure, GeneralTreatment
            $table->string('procedure_title', 255);
            $table->text('explanation_of_risks');
            $table->text('alternative_treatments')->nullable();
            $table->string('signatory_type', 30)->default('Patient'); // Patient, NextOfKin, Guardian, MedicalPowerOfAttorney
            $table->string('signatory_name', 150);
            $table->string('signature_fingerprint_token', 255)->nullable();
            $table->string('witness_name', 150)->nullable();
            $table->boolean('interpreter_used')->default(false);
            $table->string('language_used', 50)->default('Swahili');
            $table->timestamp('signed_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('set null');
            $table->foreign('procedure_order_id')->references('id')->on('procedure_orders')->onDelete('set null');
            $table->foreign('clinician_id')->references('id')->on('users')->onDelete('restrict');
        });

        // 2. Inter-Facility Clinical Referrals (MoH Standard Form)
        Schema::create('clinical_referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('from_facility_id');
            $table->uuid('to_facility_id')->nullable(); // If referring to internal facility
            $table->string('external_facility_name', 255)->nullable(); // If referring outside tenant
            $table->uuid('patient_id');
            $table->uuid('encounter_id')->nullable();
            $table->uuid('referring_doctor_id');

            $table->string('referral_number', 50); // e.g. REF-2026-000123
            $table->string('urgency', 20)->default('Routine'); // Routine, Urgent, Emergency
            $table->string('specialty_required', 100);
            $table->text('clinical_summary');
            $table->text('investigations_performed')->nullable();
            $table->text('treatments_given')->nullable();
            $table->text('reason_for_referral');
            $table->string('transport_mode', 50)->nullable(); // Ambulance, Private, Public
            $table->string('status', 30)->default('Draft'); // Draft, Dispatched, Accepted, Completed, Rejected
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('from_facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('to_facility_id')->references('id')->on('facilities')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('set null');
            $table->foreign('referring_doctor_id')->references('id')->on('users')->onDelete('restrict');

            $table->unique(['tenant_id', 'referral_number']);
        });

        // 3. Child & Adult Immunization Registry (EPI Schedule)
        Schema::create('patient_immunizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('patient_id');
            $table->uuid('administered_by');
            $table->uuid('encounter_id')->nullable();

            $table->string('vaccine_code', 30); // BCG, OPV-0, Pentavalent-1, Rota-1, PCV-1, Measles-Rubella
            $table->string('vaccine_name', 100);
            $table->integer('dose_number')->default(1);
            $table->string('batch_number', 50)->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('administration_site', 50)->nullable(); // Left Deltoid, Right Anterolateral Thigh, Oral
            $table->string('route', 30)->default('Intramuscular'); // Intramuscular, Subcutaneous, Oral, Intradermal
            $table->text('adverse_reaction_notes')->nullable();
            $table->timestamp('administered_at')->useCurrent();
            $table->date('next_due_date')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('administered_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('set null');

            $table->index(['tenant_id', 'patient_id', 'vaccine_code']);
        });

        // 4. Antenatal Care (ANC) & Maternal Telemetry
        Schema::create('anc_encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('encounter_id');
            $table->uuid('patient_id');
            $table->uuid('midwife_id');

            $table->integer('gravida')->default(1);
            $table->integer('para')->default(0);
            $table->date('last_menstrual_period')->nullable();
            $table->date('estimated_date_of_delivery')->nullable();
            $table->integer('gestational_age_weeks')->nullable();
            $table->decimal('fundal_height_cm', 5, 2)->nullable();
            $table->string('fetal_presentation', 50)->nullable(); // Cephalic, Breech, Transverse
            $table->integer('fetal_heart_rate_bpm')->nullable();
            $table->string('fetal_movement', 30)->nullable(); // Normal, Reduced, Absent
            $table->decimal('urinary_protein', 5, 2)->nullable(); // Pre-eclampsia screening
            $table->string('iptp_malaria_dose', 20)->nullable(); // SP-1, SP-2, SP-3 (Tanzania guideline)
            $table->boolean('iron_folate_given')->default(true);
            $table->boolean('high_risk_flag')->default(false);
            $table->text('high_risk_reason')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('midwife_id')->references('id')->on('users')->onDelete('restrict');
        });

        // 5. Partograph Labor Monitoring (Labor Room Telemetry)
        Schema::create('partograph_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('anc_encounter_id')->nullable();
            $table->uuid('encounter_id');
            $table->uuid('patient_id');
            $table->uuid('recorded_by');

            $table->decimal('cervical_dilation_cm', 4, 1); // 0 to 10 cm
            $table->integer('fetal_heart_rate_bpm'); // 110 to 160 bpm normal
            $table->string('liquor_status', 30)->default('Intact'); // Intact, Clear, Meconium, Blood
            $table->string('fetal_head_descent', 20)->nullable(); // 5/5 to 0/5
            $table->integer('uterine_contractions_per_10min')->default(0);
            $table->integer('contraction_duration_seconds')->default(0);
            $table->decimal('maternal_systolic_bp', 5, 2)->nullable();
            $table->decimal('maternal_diastolic_bp', 5, 2)->nullable();
            $table->integer('maternal_pulse_bpm')->nullable();
            $table->boolean('alert_line_crossed')->default(false);
            $table->boolean('action_line_crossed')->default(false);
            $table->text('midwife_remarks')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('anc_encounter_id')->references('id')->on('anc_encounters')->onDelete('set null');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clinical_consents ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE clinical_consents FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_policy ON clinical_consents FOR ALL USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID) WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);");

            DB::statement('ALTER TABLE clinical_referrals ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE clinical_referrals FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_policy ON clinical_referrals FOR ALL USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID) WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);");

            DB::statement('ALTER TABLE patient_immunizations ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE patient_immunizations FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_policy ON patient_immunizations FOR ALL USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID) WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);");

            DB::statement('ALTER TABLE anc_encounters ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE anc_encounters FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_policy ON anc_encounters FOR ALL USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID) WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);");

            DB::statement('ALTER TABLE partograph_entries ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE partograph_entries FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_policy ON partograph_entries FOR ALL USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID) WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partograph_entries');
        Schema::dropIfExists('anc_encounters');
        Schema::dropIfExists('patient_immunizations');
        Schema::dropIfExists('clinical_referrals');
        Schema::dropIfExists('clinical_consents');
    }
};
