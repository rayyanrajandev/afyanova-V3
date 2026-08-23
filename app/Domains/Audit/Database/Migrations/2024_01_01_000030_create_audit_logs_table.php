<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('facility_id')->nullable();
            $table->uuid('user_id')->nullable();

            $table->string('event_category'); // CLINICAL, FINANCIAL, INVENTORY, AUTH, SECURITY, PRIVACY
            $table->string('action'); // CREATE, UPDATE, AMEND, SIGN, VOID, VIEW_CHART
            $table->string('entity_type');
            $table->uuid('entity_id');

            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            $table->string('route_name')->nullable();

            $table->jsonb('before_state')->nullable();
            $table->jsonb('after_state')->nullable();
            $table->text('justification_reason')->nullable();

            $table->string('hash_signature', 64);
            $table->string('previous_hash', 64)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });

        // Compound indexes for fast compliance audits
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_audit_tenant_entity ON audit_logs (tenant_id, entity_type, entity_id, created_at DESC)');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_audit_tenant_user ON audit_logs (tenant_id, user_id, created_at DESC)');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_audit_tenant_category ON audit_logs (tenant_id, event_category, created_at DESC)');
        }

        // Apply RLS
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY');
        } if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_logs FORCE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON audit_logs
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }

        // Prevent UPDATE or DELETE on audit logs table
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE RULE no_update_audit AS ON UPDATE TO audit_logs DO INSTEAD NOTHING');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE RULE no_delete_audit AS ON DELETE TO audit_logs DO INSTEAD NOTHING');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP RULE IF EXISTS no_delete_audit ON audit_logs');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP RULE IF EXISTS no_update_audit ON audit_logs');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON audit_logs');
        }
        Schema::dropIfExists('audit_logs');
    }
};
