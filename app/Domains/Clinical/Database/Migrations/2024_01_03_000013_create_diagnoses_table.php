<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('encounter_id');
            $table->uuid('patient_id');
            $table->uuid('diagnosed_by');

            $table->string('icd_10_code', 20)->nullable();
            $table->string('description');
            $table->string('certainty', 30)->default('Confirmed'); // Suspected, Confirmed, Ruled Out
            $table->string('type', 30)->default('Primary'); // Primary, Secondary, Comorbidity
            $table->text('notes')->nullable();

            // Immutability Amendment Pattern
            $table->boolean('is_amendment')->default(false);
            $table->uuid('amended_diagnosis_id')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->boolean('is_deprecated')->default(false);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('diagnosed_by')->references('id')->on('users')->onDelete('restrict');
        });

        // Self-referencing FK added after the table exists — see the
        // patients migration for why this can't live inside Schema::create().
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->foreign('amended_diagnosis_id')->references('id')->on('diagnoses')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE diagnoses ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE diagnoses FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON diagnoses
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON diagnoses');
        }
        Schema::dropIfExists('diagnoses');
    }
};
