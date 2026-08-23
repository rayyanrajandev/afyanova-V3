<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_administration_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('item_master_id')->nullable()->constrained('item_masters')->nullOnDelete();
            $table->foreignUuid('medication_id')->nullable()->constrained('medication_formularies')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();

            $table->string('item_name');
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('dose_quantity', 12, 2)->default(1);
            $table->string('dose_unit', 50)->default('dose');
            $table->string('route', 50)->default('Oral'); // Oral, IV, IM, SC, Top, Inhalation, PR, etc.
            $table->string('frequency', 50)->nullable(); // STAT, PRN, BD, TDS, QID, OD
            $table->dateTime('scheduled_time')->nullable();
            $table->dateTime('administered_at');
            $table->foreignUuid('administered_by')->constrained('users');
            $table->foreignUuid('witness_by')->nullable()->constrained('users');
            $table->boolean('witness_pin_verified')->default(false);

            $table->string('status', 30)->default('Administered'); // Administered, Refused, Held, Missed
            $table->boolean('is_dda_narcotic')->default(false);
            $table->boolean('is_billed')->default(true);
            $table->decimal('charge_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'admission_id']);
            $table->index(['tenant_id', 'patient_id']);
            $table->index(['tenant_id', 'administered_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE medication_administration_records ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE medication_administration_records FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON medication_administration_records
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON medication_administration_records');
        }
        Schema::dropIfExists('medication_administration_records');
    }
};
