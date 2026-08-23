<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Inventory Warehouses and Physical Locations
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->string('name'); // e.g. Central Medical Warehouse
            $table->string('code')->unique(); // WH-CENTRAL, STORE-OPD, STORE-IPD, CAB-THEATRE
            $table->string('type')->default('Warehouse'); // Warehouse, PharmacyStore, WardCabinet, TheatreStore, LabStore
            $table->boolean('is_dispensing_enabled')->default(false);
            $table->boolean('is_storage_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Inventory Multi-Location Stock Balances
        Schema::create('inventory_stock_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignUuid('medication_id')->constrained('medication_formularies')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('reorder_level')->default(20);
            $table->integer('reorder_quantity')->default(100);
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'medication_id', 'batch_id'], 'inv_balance_loc_med_batch_unique');
        });

        // 3. Suppliers Master Catalog
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name'); // e.g. Medical Stores Department (MSD)
            $table->string('code')->unique(); // SUP-MSD-01
            $table->string('tin_number')->nullable();
            $table->string('vrn_number')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('physical_address')->nullable();
            $table->string('payment_terms')->default('Net30'); // COD, Net15, Net30, Net60
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Purchase Orders (PO) Header
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('po_number')->unique(); // PO-2026-XXXX
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignUuid('destination_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status')->default('Draft'); // Draft, Submitted, Approved, Partially_Received, Completed, Cancelled
            $table->decimal('subtotal', 14, 2)->default(0.00);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->string('currency', 10)->default('TZS');
            $table->foreignUuid('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Purchase Order Items
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('medication_id')->constrained('medication_formularies')->cascadeOnDelete();
            $table->integer('requested_quantity');
            $table->integer('received_quantity')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0.00);
            $table->decimal('total_cost', 14, 2)->default(0.00);
            $table->timestamps();
        });

        // 6. Goods Receipt Notes (GRN) Inward Postings
        Schema::create('goods_receipt_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('grn_number')->unique(); // GRN-2026-XXXX
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('supplier_invoice_number')->nullable();
            $table->string('delivery_note_number')->nullable();
            $table->date('received_date');
            $table->string('status')->default('Received'); // Received, Verified, Posted_To_Ledger
            $table->decimal('total_received_value', 14, 2)->default(0.00);
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 7. Goods Receipt Items (Batch Inward Record)
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('goods_receipt_note_id')->constrained('goods_receipt_notes')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignUuid('medication_id')->constrained('medication_formularies')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->string('batch_number');
            $table->date('expiry_date');
            $table->integer('received_quantity');
            $table->integer('rejected_quantity')->default(0);
            $table->decimal('unit_purchase_cost', 12, 2)->default(0.00);
            $table->decimal('unit_selling_price', 12, 2)->default(0.00);
            $table->decimal('total_cost', 14, 2)->default(0.00);
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        // 8. Inter-Store Stock Transfers (Two-Step Handshake)
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('transfer_number')->unique(); // TRF-2026-XXXX
            $table->foreignUuid('source_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignUuid('destination_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('status')->default('Draft'); // Draft, Dispatched_In_Transit, Received_Confirmed, Disputed, Cancelled
            $table->foreignUuid('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. Stock Transfer Items
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignUuid('medication_id')->constrained('medication_formularies')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('inventory_batches')->cascadeOnDelete();
            $table->integer('quantity_requested');
            $table->integer('quantity_dispatched')->default(0);
            $table->integer('quantity_received')->default(0);
            $table->string('discrepancy_reason')->nullable();
            $table->timestamps();
        });

        // 10. Stocktaking Sessions (Physical Inventory Audit)
        Schema::create('stocktake_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('session_number')->unique(); // STK-2026-XXXX
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('status')->default('In_Progress'); // In_Progress, Review_Pending, Approved_Reconciled, Cancelled
            $table->foreignUuid('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 11. Stocktake Items
        Schema::create('stocktake_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('stocktake_session_id')->constrained('stocktake_sessions')->cascadeOnDelete();
            $table->foreignUuid('medication_id')->constrained('medication_formularies')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->integer('system_expected_quantity');
            $table->integer('physical_counted_quantity');
            $table->integer('variance_quantity')->default(0);
            $table->decimal('variance_value_tzs', 14, 2)->default(0.00);
            $table->string('variance_reason')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_locations ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_locations FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON inventory_locations
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_stock_balances ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_stock_balances FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON inventory_stock_balances
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE suppliers ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE suppliers FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON suppliers
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE purchase_orders ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE purchase_orders FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON purchase_orders
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE purchase_order_items ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE purchase_order_items FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON purchase_order_items
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE goods_receipt_notes ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE goods_receipt_notes FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON goods_receipt_notes
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE goods_receipt_items ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE goods_receipt_items FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON goods_receipt_items
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_transfers ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_transfers FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON stock_transfers
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_transfer_items ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_transfer_items FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON stock_transfer_items
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stocktake_sessions ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stocktake_sessions FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON stocktake_sessions
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stocktake_items ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stocktake_items FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON stocktake_items
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON stocktake_items');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON stocktake_sessions');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON stock_transfer_items');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON stock_transfers');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON goods_receipt_items');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON goods_receipt_notes');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON purchase_order_items');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON purchase_orders');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON suppliers');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON inventory_stock_balances');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON inventory_locations');
        }
        Schema::dropIfExists('stocktake_items');
        Schema::dropIfExists('stocktake_sessions');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipt_notes');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('inventory_stock_balances');
        Schema::dropIfExists('inventory_locations');
    }
};
