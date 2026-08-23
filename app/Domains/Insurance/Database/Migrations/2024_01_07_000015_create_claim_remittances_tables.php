<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Claim Remittances (Batch payment advice from insurance payers)
        Schema::create('claim_remittances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('insurance_provider_id');
            $table->uuid('processed_by');

            $table->string('remittance_number', 50); // e.g. REM-2026-000123
            $table->string('payment_reference', 100); // Bank transfer / EFT reference
            $table->decimal('total_claimed_amount', 12, 2);
            $table->decimal('total_settled_amount', 12, 2);
            $table->decimal('total_disallowed_amount', 12, 2)->default(0.00);
            $table->string('status', 30)->default('Processed'); // Processed, Disputed, Reconciled
            $table->date('remittance_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('insurance_provider_id')->references('id')->on('insurance_providers')->onDelete('restrict');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('restrict');

            $table->unique(['tenant_id', 'remittance_number']);
        });

        // 2. Claim Remittance Items (Line-by-line adjudication per claim)
        Schema::create('claim_remittance_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('claim_remittance_id');
            $table->uuid('insurance_claim_id');

            $table->decimal('claimed_amount', 12, 2);
            $table->decimal('settled_amount', 12, 2);
            $table->decimal('disallowed_amount', 12, 2)->default(0.00);
            $table->string('disallowance_reason_code', 50)->nullable(); // e.g. TARIFF_EXCEEDED, NOT_COVERED, INVALID_DIAGNOSIS, PREAUTH_MISSING
            $table->text('disallowance_remarks')->nullable();
            $table->string('adjudication_status', 30)->default('PaidInFull'); // PaidInFull, PartiallySettled, Rejected, Disputed
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('claim_remittance_id')->references('id')->on('claim_remittances')->onDelete('cascade');
            $table->foreign('insurance_claim_id')->references('id')->on('insurance_claims')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE claim_remittances ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE claim_remittances FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_policy ON claim_remittances FOR ALL USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID) WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);");

            DB::statement('ALTER TABLE claim_remittance_items ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE claim_remittance_items FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_policy ON claim_remittance_items FOR ALL USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID) WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_remittance_items');
        Schema::dropIfExists('claim_remittances');
    }
};
