<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id')->nullable();
            $table->uuid('ward_id');

            $table->string('bed_number'); // e.g. BED-101, ICU-02
            $table->string('bed_type')->default('Standard'); // Standard, ICU_Electric, Oxygen_Equipped, Bassinet, VIP_Suite
            $table->decimal('daily_rate_amount', 12, 2)->default(25000.00);
            $table->string('status')->default('Available'); // Available, Occupied, Cleaning, Maintenance, Reserved
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('ward_id')->references('id')->on('wards')->onDelete('cascade');

            $table->index(['tenant_id', 'ward_id', 'status'], 'idx_ward_bed_status');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE beds ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE beds FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON beds
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON beds');
        }
        Schema::dropIfExists('beds');
    }
};
