<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_masters', function (Blueprint $table) {
            $table->index(['tenant_id', 'category', 'item_code'], 'idx_items_tenant_cat_code');
            $table->index(['tenant_id', 'name'], 'idx_items_tenant_name');
        });
    }

    public function down(): void
    {
        Schema::table('item_masters', function (Blueprint $table) {
            $table->dropIndex('idx_items_tenant_cat_code');
            $table->dropIndex('idx_items_tenant_name');
        });
    }
};
