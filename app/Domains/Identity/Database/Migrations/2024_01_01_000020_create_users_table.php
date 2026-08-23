<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password_hash');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('professional_registration_no')->nullable(); // MCT number
            $table->string('status')->default('active'); // active, suspended, deactivated
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
        });

        Schema::create('user_facility_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('facility_id');
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->unique(['user_id', 'facility_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Apply RLS to users. Deliberately NOT FORCE here, unlike every
        // other tenant-scoped table: authentication has to look a user up
        // by email before any tenant is known at all (email is globally
        // unique by design — see the unique() constraint above — precisely
        // so a login can resolve both the user AND their tenant from one
        // exact-match lookup). FORCE would apply RLS to the app's own role
        // too, which owns this table, and block that lookup outright since
        // app.current_tenant_id genuinely cannot be set yet at that point
        // in the request. Every other access path to users (listings, role
        // assignment, anything post-authentication) still goes through
        // BelongsToTenant's Eloquent scope and the authorization layer;
        // this exception is scoped to the one operation that structurally
        // cannot have a tenant filter applied to it yet.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
            CREATE POLICY tenant_isolation_policy ON users
            FOR ALL
            USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
            WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
        ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON users');
        }
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('user_facility_assignments');
        Schema::dropIfExists('users');
    }
};
