<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance Tenants Table with Subscription Tiers & Feature Flags
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('subscription_tier', 50)->default('growth')->after('plan'); // starter, growth, enterprise
            $table->string('subscription_status', 50)->default('active')->after('status'); // trial, active, past_due, suspended, cancelled
            $table->unsignedInteger('max_facilities')->default(5)->after('subscription_tier');
            $table->unsignedInteger('max_users')->default(50)->after('max_facilities');
            $table->unsignedInteger('storage_quota_mb')->default(10240)->after('max_users'); // 10 GB default
            $table->jsonb('feature_flags')->nullable()->after('settings'); // ['fhir', 'dicom', 'mpesa', 'sms', 'insurance', 'bi_analytics', 'theatre', 'inpatient']
            $table->string('billing_cycle', 20)->default('monthly')->after('feature_flags'); // monthly, annually
            $table->string('billing_contact_email', 150)->nullable()->after('billing_cycle');
            $table->string('billing_contact_phone', 50)->nullable()->after('billing_contact_email');
            $table->timestamp('trial_ends_at')->nullable()->after('billing_contact_phone');
        });

        // 2. Create Impersonation Logs Table for Audited Superadmin Sessions
        Schema::create('impersonation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('superadmin_user_id')->index();
            $table->uuid('impersonated_user_id')->index();
            $table->uuid('impersonated_tenant_id')->index();
            $table->text('justification_reason');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('superadmin_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('impersonated_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('impersonated_tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_logs');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_tier',
                'subscription_status',
                'max_facilities',
                'max_users',
                'storage_quota_mb',
                'feature_flags',
                'billing_cycle',
                'billing_contact_email',
                'billing_contact_phone',
                'trial_ends_at',
            ]);
        });
    }
};
