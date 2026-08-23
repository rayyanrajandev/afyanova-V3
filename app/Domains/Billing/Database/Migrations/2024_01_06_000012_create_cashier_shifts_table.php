<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('user_id'); // Cashier

            $table->string('shift_number')->unique();
            $table->string('status', 30)->default('Open'); // Open, Closed, Reconciled
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();

            $table->decimal('opening_float', 15, 2)->default(0.00);
            $table->decimal('closing_cash_counted', 15, 2)->nullable();
            $table->decimal('expected_cash_total', 15, 2)->default(0.00);
            $table->decimal('discrepancy', 15, 2)->default(0.00);
            $table->string('variance_status', 30)->default('Pending'); // Pending, Balanced, Overage, Shortage

            $table->text('notes')->nullable();
            $table->uuid('reconciled_by')->nullable(); // Supervisor
            $table->timestamp('reconciled_at')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reconciled_by')->references('id')->on('users')->onDelete('set null');
        });

        // Apply RLS for PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cashier_shifts ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE cashier_shifts FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON cashier_shifts
                FOR ALL
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON cashier_shifts');
        }
        Schema::dropIfExists('cashier_shifts');
    }
};
