<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('patient_id');
            $table->uuid('encounter_id')->nullable(); // Links to the clinical encounter if already started

            $table->string('ticket_number', 20); // E.g., A-101
            $table->string('priority', 20)->default('Routine'); // Routine, Urgent, Emergency
            $table->string('current_service_point', 50); // Triage, Doctor Room 1, Lab, Pharmacy, Billing
            $table->string('status', 20)->default('Waiting'); // Waiting, In Progress, Completed, Abandoned

            $table->timestamp('joined_queue_at')->useCurrent();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('set null');
        });

        // Apply RLS
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE queue_tickets ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE queue_tickets FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON queue_tickets
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON queue_tickets');
        }
        Schema::dropIfExists('queue_tickets');
    }
};
