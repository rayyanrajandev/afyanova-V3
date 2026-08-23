<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('primary_mrn', 32);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->date('dob')->nullable();
            $table->string('gender', 20); // Male, Female, Other, Unknown
            $table->string('blood_group', 10)->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('nationality', 50)->nullable();
            $table->string('status', 20)->default('Active'); // Active, Deceased, Merged
            $table->uuid('merged_into_patient_id')->nullable();
            // The facility that registered this patient — nullable because
            // a patient predating this column, or registered through a
            // path with no facility context, has no restriction applied
            // (see Patient::booted()'s facility visibility scope). Not a
            // hard ownership boundary: a patient legitimately seen at
            // another facility (referral, transfer) stays visible there
            // too, via that facility having its own Encounter with them.
            $table->uuid('registered_at_facility_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('registered_at_facility_id')->references('id')->on('facilities')->onDelete('set null');

            $table->unique(['tenant_id', 'primary_mrn']);
        });

        // Self-referencing FK must be added after the table (and its
        // primary key) exists — Postgres compiles a chained ->primary()
        // as an ALTER TABLE that runs AFTER any foreign keys declared
        // inside the same Schema::create() block, so a self-reference
        // declared there fails with "no unique constraint matching
        // given keys" because its own primary key doesn't exist yet.
        Schema::table('patients', function (Blueprint $table) {
            $table->foreign('merged_into_patient_id')->references('id')->on('patients')->onDelete('restrict');
        });

        // Apply RLS
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE patients ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE patients FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON patients
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON patients');
        }
        Schema::dropIfExists('patients');
    }
};
