<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id')->nullable();

            $table->string('name'); // e.g. Male Medical Ward, Maternity Wing, ICU
            $table->string('code')->unique(); // e.g. MMW-01, MAT-01, ICU-01
            $table->string('ward_type')->default('General'); // General, Private, ICU, Maternity, Surgical, Pediatric
            $table->string('gender_restriction')->default('Mixed'); // MaleOnly, FemaleOnly, Mixed, Pediatric
            $table->string('floor_location')->nullable();
            $table->decimal('daily_base_rate', 12, 2)->default(25000.00);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wards ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wards FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON wards
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON wards');
        }
        Schema::dropIfExists('wards');
    }
};
