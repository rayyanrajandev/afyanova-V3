<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Payments (Individual tender transaction receipts)
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('invoice_id');
            $table->uuid('cashier_shift_id')->nullable();
            $table->uuid('user_id');

            $table->string('receipt_number', 50); // e.g. RCP-2026-000123
            $table->string('payment_method', 50); // Cash, Lipa Namba, M-Pesa, Tigo Pesa, Airtel Money, Card, Bank POS, Deposit Drawdown, Insurance
            $table->decimal('amount', 12, 2);
            $table->string('transaction_reference', 100)->nullable(); // Gateway code or bank slip
            $table->string('status', 30)->default('Completed'); // Completed, Reversed, Refunded
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('cashier_shift_id')->references('id')->on('cashier_shifts')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['tenant_id', 'invoice_id']);
        });

        // 2. Patient Advance Deposits (Prepayment wallets)
        Schema::create('patient_deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('patient_id');
            $table->uuid('user_id');
            $table->uuid('cashier_shift_id')->nullable();

            $table->string('deposit_number', 50); // e.g. DEP-2026-000123
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_remaining', 12, 2);
            $table->string('payment_method', 50);
            $table->string('transaction_reference', 100)->nullable();
            $table->string('status', 30)->default('Active'); // Active, Depleted, Refunded
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('cashier_shift_id')->references('id')->on('cashier_shifts')->onDelete('set null');

            $table->unique(['tenant_id', 'deposit_number']);
            $table->index(['tenant_id', 'patient_id', 'status']);
        });

        // 3. Deposit Allocations (Drawdowns applied to Invoices)
        Schema::create('patient_deposit_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('deposit_id');
            $table->uuid('invoice_id');
            $table->uuid('payment_id')->nullable();
            $table->uuid('allocated_by');

            $table->decimal('allocated_amount', 12, 2);
            $table->timestamp('allocated_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('deposit_id')->references('id')->on('patient_deposits')->onDelete('restrict');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
            $table->foreign('allocated_by')->references('id')->on('users')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE payments FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_policy ON payments FOR ALL USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID) WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);");

            DB::statement('ALTER TABLE patient_deposits ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE patient_deposits FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_policy ON patient_deposits FOR ALL USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID) WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);");

            DB::statement('ALTER TABLE patient_deposit_allocations ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE patient_deposit_allocations FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_policy ON patient_deposit_allocations FOR ALL USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID) WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_deposit_allocations');
        Schema::dropIfExists('patient_deposits');
        Schema::dropIfExists('payments');
    }
};
