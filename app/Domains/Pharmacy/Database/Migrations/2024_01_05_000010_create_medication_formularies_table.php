<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_formularies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            $table->string('generic_name');
            $table->string('brand_name')->nullable();
            $table->string('form'); // Tablet, Capsule, Syrup, Injection
            $table->string('strength'); // 500mg, 10mg/ml
            $table->string('route'); // PO, IV, IM
            $table->string('drug_class')->nullable(); // e.g., Penicillin (used for allergy checking)
            $table->string('charge_code')->nullable(); // links to charge_master_items.code for per-unit pricing

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
        });

        // Apply RLS
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE medication_formularies ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE medication_formularies FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON medication_formularies
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON medication_formularies');
        }
        Schema::dropIfExists('medication_formularies');
    }
};
