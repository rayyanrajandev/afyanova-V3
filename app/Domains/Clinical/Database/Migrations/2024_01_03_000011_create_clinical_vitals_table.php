<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_vitals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('encounter_id');
            $table->uuid('patient_id');
            $table->uuid('recorded_by');

            // Core vitals
            $table->decimal('temperature_c', 5, 2)->nullable();
            $table->integer('heart_rate')->nullable();
            $table->integer('systolic_bp')->nullable();
            $table->integer('diastolic_bp')->nullable();
            $table->integer('respiratory_rate')->nullable();
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->text('notes')->nullable();

            // Immutability Amendment Pattern
            $table->boolean('is_amendment')->default(false);
            $table->uuid('amended_vital_id')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->boolean('is_deprecated')->default(false);

            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('restrict');
        });

        // Self-referencing FK added after the table exists — see the
        // patients migration for why this can't live inside Schema::create().
        Schema::table('clinical_vitals', function (Blueprint $table) {
            $table->foreign('amended_vital_id')->references('id')->on('clinical_vitals')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clinical_vitals ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clinical_vitals FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON clinical_vitals
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON clinical_vitals');
        }
        Schema::dropIfExists('clinical_vitals');
    }
};
