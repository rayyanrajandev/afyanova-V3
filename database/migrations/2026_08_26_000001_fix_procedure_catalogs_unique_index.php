<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_catalogs', function (Blueprint $table) {
            // Drop global unique constraint on procedure_code if it exists
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE procedure_catalogs DROP CONSTRAINT IF EXISTS procedure_catalogs_procedure_code_unique');
                DB::statement('DROP INDEX IF EXISTS procedure_catalogs_procedure_code_unique');
            }

            // Add composite unique on tenant_id + procedure_code
            $table->unique(['tenant_id', 'procedure_code'], 'procedure_catalogs_tenant_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('procedure_catalogs', function (Blueprint $table) {
            $table->dropUnique('procedure_catalogs_tenant_code_unique');
            $table->unique('procedure_code', 'procedure_catalogs_procedure_code_unique');
        });
    }
};
