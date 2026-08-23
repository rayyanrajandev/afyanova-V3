<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Insurance Providers Master (NHIF, Jubilee, Strategis, AAR, etc.)
        Schema::create('insurance_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code')->unique(); // NHIF, JUBILEE, STRATEGIS, AAR
            $table->string('name');
            $table->string('provider_type')->default('NationalScheme'); // NationalScheme, PrivateHMO, CorporateSelfFunded
            $table->string('api_adapter')->default('generic_adapter'); // nhif_adapter, jubilee_adapter, generic_adapter
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Benefit Schemes under a Provider (e.g. NHIF Civil Servants, Jubilee Corporate Gold)
        Schema::create('insurance_schemes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('insurance_provider_id')->constrained('insurance_providers')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('co_pay_type')->default('None'); // None, FixedAmount, Percentage
            $table->decimal('co_pay_amount', 12, 2)->default(0.00);
            $table->decimal('annual_limit_amount', 14, 2)->nullable();
            $table->boolean('requires_pre_auth')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Patient Membership Policies / Cards
        Schema::create('patient_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('insurance_provider_id')->constrained('insurance_providers')->cascadeOnDelete();
            $table->foreignUuid('insurance_scheme_id')->nullable()->constrained('insurance_schemes')->nullOnDelete();
            $table->string('card_number')->index();
            $table->string('principal_member_name')->nullable();
            $table->string('principal_member_number')->nullable();
            $table->string('relationship')->default('Self'); // Self, Spouse, Child, Dependent
            $table->date('policy_start_date')->nullable();
            $table->date('policy_expiry_date')->nullable();
            $table->string('status')->default('Active'); // Active, Suspended, Expired
            $table->boolean('biometric_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // 4. Negotiated Insurance Tariffs (NHIF / Private price schedule)
        Schema::create('insurance_tariffs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('insurance_provider_id')->constrained('insurance_providers')->cascadeOnDelete();
            $table->foreignUuid('insurance_scheme_id')->nullable()->constrained('insurance_schemes')->nullOnDelete();
            $table->string('item_type'); // Consultation, Bed, LabTest, Medication, Procedure
            $table->string('item_code');
            $table->string('item_name');
            $table->decimal('tariff_price', 12, 2)->default(0.00);
            $table->boolean('is_covered')->default(true);
            $table->boolean('requires_prior_approval')->default(false);
            $table->timestamps();
        });

        // 5. Pre-Authorization Requests & Approvals (TAR / Prior Approvals)
        Schema::create('pre_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('patient_policy_id')->constrained('patient_policies')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->string('auth_code')->unique();
            $table->string('procedure_description');
            $table->decimal('requested_amount', 12, 2)->default(0.00);
            $table->decimal('approved_amount', 12, 2)->default(0.00);
            $table->string('status')->default('Approved'); // Requested, Approved, Denied, Expired
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. Insurance Claims (Header)
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('claim_number')->unique(); // CLM-2026-XXXX
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('patient_policy_id')->constrained('patient_policies')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('total_claimed_amount', 12, 2)->default(0.00);
            $table->decimal('co_pay_amount', 12, 2)->default(0.00);
            $table->decimal('approved_amount', 12, 2)->default(0.00);
            $table->string('status')->default('Draft'); // Draft, Vetted, Submitted, Approved, Queried, Partially_Paid, Rejected, Paid
            $table->boolean('scrubber_passed')->default(false);
            $table->json('scrubber_errors')->nullable();
            $table->string('batch_number')->nullable()->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('adjudicated_at')->nullable();
            $table->timestamps();
        });

        // 7. Insurance Claim Items (Line item breakdown)
        Schema::create('insurance_claim_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('insurance_claim_id')->constrained('insurance_claims')->cascadeOnDelete();
            $table->string('item_type'); // Consultation, Bed, Lab, Pharmacy, Procedure
            $table->string('item_code')->nullable();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->decimal('claimed_amount', 12, 2)->default(0.00);
            $table->decimal('approved_amount', 12, 2)->default(0.00);
            $table->string('status')->default('Claimed'); // Claimed, Approved, Disallowed
            $table->string('disallowance_reason')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insurance_providers ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insurance_providers FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON insurance_providers
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insurance_schemes ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insurance_schemes FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON insurance_schemes
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE patient_policies ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE patient_policies FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON patient_policies
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insurance_tariffs ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insurance_tariffs FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON insurance_tariffs
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pre_authorizations ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pre_authorizations FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON pre_authorizations
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insurance_claims ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insurance_claims FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON insurance_claims
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insurance_claim_items ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE insurance_claim_items FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON insurance_claim_items
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON insurance_claim_items');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON insurance_claims');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON pre_authorizations');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON insurance_tariffs');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON patient_policies');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON insurance_schemes');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON insurance_providers');
        }
        Schema::dropIfExists('insurance_claim_items');
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('pre_authorizations');
        Schema::dropIfExists('insurance_tariffs');
        Schema::dropIfExists('patient_policies');
        Schema::dropIfExists('insurance_schemes');
        Schema::dropIfExists('insurance_providers');
    }
};
