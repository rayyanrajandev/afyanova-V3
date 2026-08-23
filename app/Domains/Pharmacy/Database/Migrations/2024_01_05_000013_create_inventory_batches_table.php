<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id')->nullable();
            $table->uuid('medication_id');

            $table->string('batch_number');
            $table->string('barcode')->nullable();
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date');

            $table->integer('initial_quantity');
            $table->integer('current_quantity');
            $table->decimal('unit_cost', 12, 2)->default(0.00);
            $table->decimal('unit_selling_price', 12, 2)->default(0.00);
            $table->string('supplier_name')->nullable(); // e.g. MSD, Zenufa, Shelys
            $table->string('status')->default('Active'); // Active, Quarantined, Expired, Depleted
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('medication_id')->references('id')->on('medication_formularies')->onDelete('restrict');

            $table->index(['tenant_id', 'medication_id', 'status', 'expiry_date'], 'idx_fefo_lookup');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_batches ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_batches FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON inventory_batches
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON inventory_batches');
        }
        Schema::dropIfExists('inventory_batches');
    }
};
