<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_master_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // e.g. CONSULT-OPD, LAB-FBP — deliberately not unique alone: the
            // same code legitimately repeats across tenants, and can repeat
            // within one tenant as a new effective-dated price version.
            $table->string('code');
            $table->string('name');
            $table->string('category', 50); // Consultation, Pharmacy, Lab, Procedure, Nursing, Bed
            $table->decimal('unit_price', 15, 2);
            $table->string('currency', 3)->default('TZS');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->index(['tenant_id', 'code', 'effective_from']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE charge_master_items ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE charge_master_items FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON charge_master_items
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON charge_master_items');
        }
        Schema::dropIfExists('charge_master_items');
    }
};
