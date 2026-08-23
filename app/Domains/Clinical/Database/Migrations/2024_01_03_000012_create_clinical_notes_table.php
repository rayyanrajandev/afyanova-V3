<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('encounter_id');
            $table->uuid('patient_id');
            $table->uuid('author_id');

            $table->string('note_type', 50)->default('SOAP'); // SOAP, Consult, Procedure
            $table->jsonb('content'); // { "subjective": "...", "objective": "...", "assessment": "...", "plan": "..." }

            // Immutability logic
            $table->boolean('is_signed')->default(false);
            $table->timestamp('signed_at')->nullable();

            // Amendment Pattern
            $table->boolean('is_amendment')->default(false);
            $table->uuid('amended_note_id')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->boolean('is_deprecated')->default(false);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('encounter_id')->references('id')->on('encounters')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
        });

        // Self-referencing FK added after the table exists — see the
        // patients migration for why this can't live inside Schema::create().
        Schema::table('clinical_notes', function (Blueprint $table) {
            $table->foreign('amended_note_id')->references('id')->on('clinical_notes')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clinical_notes ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clinical_notes FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON clinical_notes
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON clinical_notes');
        }
        Schema::dropIfExists('clinical_notes');
    }
};
