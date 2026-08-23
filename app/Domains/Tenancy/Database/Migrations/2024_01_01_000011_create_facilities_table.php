<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('facility_type')->default('clinic'); // clinic, hospital, diagnostic_center
            $table->string('license_number')->nullable();
            $table->string('hfr_code')->nullable(); // Health Facility Registry TZ
            $table->text('physical_address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'hfr_code']);
        });

        // Apply RLS
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE facilities ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE facilities FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON facilities
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON facilities');
        }
        Schema::dropIfExists('facilities');
    }
};
