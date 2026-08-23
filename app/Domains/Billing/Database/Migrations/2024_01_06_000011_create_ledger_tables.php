<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of Accounts
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            $table->string('code', 20); // E.g., 1000
            $table->string('name'); // Cash, Accounts Receivable, Pharmacy Revenue
            $table->string('type', 20); // Asset, Liability, Equity, Revenue, Expense

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->unique(['tenant_id', 'code']);
        });

        // The Transaction (Journal Entry)
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('user_id'); // Cashier/User who posted it

            $table->string('reference_type')->nullable(); // e.g., Invoice, Refund
            $table->uuid('reference_id')->nullable();

            $table->text('description');
            $table->timestamp('posted_at')->useCurrent();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });

        // The Debits and Credits
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('transaction_id');
            $table->uuid('account_id');

            $table->decimal('debit', 15, 2)->default(0.00);
            $table->decimal('credit', 15, 2)->default(0.00);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('transaction_id')->references('id')->on('ledger_transactions')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('ledger_accounts')->onDelete('restrict');
        });

        // Apply RLS
        $tables = ['ledger_accounts', 'ledger_transactions', 'ledger_entries'];
        foreach ($tables as $t) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
            }
            // Without FORCE, Postgres exempts the table owner from its own
            // RLS policies — and the app's migration role IS the owner of
            // every table it creates, so RLS would silently do nothing.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
            }
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$t}
                FOR ALL
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            ");
            }
        }
    }

    public function down(): void
    {
        $tables = ['ledger_entries', 'ledger_transactions', 'ledger_accounts'];
        foreach ($tables as $t) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$t}");
            }
            Schema::dropIfExists($t);
        }
    }
};
