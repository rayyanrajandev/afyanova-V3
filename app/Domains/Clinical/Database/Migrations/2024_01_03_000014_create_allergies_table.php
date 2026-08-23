<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('patient_id');
            $table->uuid('recorded_by');

            $table->string('allergen_type', 50); // Drug, Food, Environmental, Other
            $table->string('allergen');
            $table->string('reaction')->nullable();
            $table->string('severity', 30)->default('Moderate'); // Mild, Moderate, Severe, Life-Threatening
            $table->string('status', 30)->default('Active'); // Active, Inactive, Resolved, Error

            // Immutability logic: Instead of updating or deleting allergies to hide history,
            // the status is changed, and the edit logic applies.
            $table->boolean('is_amendment')->default(false);
            $table->uuid('amended_allergy_id')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->boolean('is_deprecated')->default(false);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('restrict');
        });

        // Self-referencing FK added after the table exists — see the
        // patients migration for why this can't live inside Schema::create().
        Schema::table('allergies', function (Blueprint $table) {
            $table->foreign('amended_allergy_id')->references('id')->on('allergies')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE allergies ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE allergies FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON allergies
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON allergies');
        }
        Schema::dropIfExists('allergies');
    }
};
