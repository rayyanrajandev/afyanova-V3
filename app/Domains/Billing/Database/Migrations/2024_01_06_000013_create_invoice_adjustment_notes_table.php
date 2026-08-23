<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_adjustment_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id');
            $table->uuid('invoice_id');

            $table->string('type', 10); // Credit, Debit
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->uuid('created_by');

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE invoice_adjustment_notes ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE invoice_adjustment_notes FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON invoice_adjustment_notes
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }

        // Adjustment notes are themselves an immutable correction record —
        // never edited or deleted once issued.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE RULE no_update_invoice_adjustment_notes AS ON UPDATE TO invoice_adjustment_notes DO INSTEAD NOTHING');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE RULE no_delete_invoice_adjustment_notes AS ON DELETE TO invoice_adjustment_notes DO INSTEAD NOTHING');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP RULE IF EXISTS no_delete_invoice_adjustment_notes ON invoice_adjustment_notes');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP RULE IF EXISTS no_update_invoice_adjustment_notes ON invoice_adjustment_notes');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON invoice_adjustment_notes');
        }
        Schema::dropIfExists('invoice_adjustment_notes');
    }
};
