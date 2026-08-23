<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bed_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id')->nullable();
            $table->uuid('admission_id');

            $table->uuid('from_ward_id');
            $table->uuid('from_bed_id');
            $table->uuid('to_ward_id');
            $table->uuid('to_bed_id');

            $table->timestamp('transferred_at')->useCurrent();
            $table->uuid('transferred_by');
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('cascade');
            $table->foreign('from_ward_id')->references('id')->on('wards')->onDelete('restrict');
            $table->foreign('from_bed_id')->references('id')->on('beds')->onDelete('restrict');
            $table->foreign('to_ward_id')->references('id')->on('wards')->onDelete('restrict');
            $table->foreign('to_bed_id')->references('id')->on('beds')->onDelete('restrict');
            $table->foreign('transferred_by')->references('id')->on('users')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bed_transfers ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bed_transfers FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON bed_transfers
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON bed_transfers');
        }
        Schema::dropIfExists('bed_transfers');
    }
};
