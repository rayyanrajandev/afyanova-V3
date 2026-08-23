<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('encounter_id');
            $table->uuid('patient_id');
            $table->uuid('prescriber_id');
            $table->uuid('medication_id');

            $table->string('dosage'); // e.g., 500mg
            $table->string('frequency'); // e.g., BID (twice a day)
            $table->integer('duration_days');
            $table->string('route'); // e.g., PO
            $table->integer('quantity'); // Total amount to dispense
            $table->text('instructions')->nullable();

            $table->string('status', 30)->default('Pending'); // Pending, Verified, Dispensed, Rejected, Cancelled

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('prescriber_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('medication_id')->references('id')->on('medication_formularies')->onDelete('restrict');
        });

        // Apply RLS
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE prescriptions ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE prescriptions FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON prescriptions
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON prescriptions');
        }
        Schema::dropIfExists('prescriptions');
    }
};
