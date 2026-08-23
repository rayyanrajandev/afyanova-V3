<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_identifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('patient_id');
            $table->uuid('facility_id')->nullable();
            $table->string('type', 50); // NIDA, NHIF_CARD_NO, PASSPORT, VOTER_ID, OLD_SYSTEM_ID
            // text, not string(100): the stored value is an encrypted
            // envelope (IV + ciphertext + MAC, base64/JSON-wrapped), which
            // runs well past 100 characters even for a short plaintext.
            $table->text('identifier_value');
            $table->string('identifier_lookup_hash', 64)->nullable(); // deterministic HMAC of identifier_value, for equality lookups since the value itself is encrypted at rest
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');

            // Scoped per tenant, not globally — a global constraint would let
            // one tenant's insert fail (or worse, leak existence) because of
            // another, unrelated tenant's data, and identifier types like
            // OLD_SYSTEM_ID were never meant to be unique across hospitals.
            $table->unique(['tenant_id', 'type', 'identifier_lookup_hash']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE patient_identifiers ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE patient_identifiers FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON patient_identifiers
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON patient_identifiers');
        }
        Schema::dropIfExists('patient_identifiers');
    }
};
