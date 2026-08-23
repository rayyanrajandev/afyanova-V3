<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id')->nullable();
            $table->uuid('medication_id');
            $table->uuid('batch_id')->nullable();

            $table->string('movement_type'); // Received, Dispensed, Adjustment_Positive, Adjustment_Negative, Expired_Disposal, Transfer
            $table->integer('quantity_change'); // e.g. +500, -24
            $table->integer('quantity_before');
            $table->integer('quantity_after');

            $table->string('reference_type')->nullable(); // Prescription, DispenseEvent, PurchaseOrder, ManualStockCount, Disposal
            $table->uuid('reference_id')->nullable();

            $table->uuid('performed_by');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('medication_id')->references('id')->on('medication_formularies')->onDelete('restrict');
            $table->foreign('batch_id')->references('id')->on('inventory_batches')->onDelete('restrict');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['tenant_id', 'medication_id', 'created_at'], 'idx_stock_mv_med');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON stock_movements
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON stock_movements');
        }
        Schema::dropIfExists('stock_movements');
    }
};
