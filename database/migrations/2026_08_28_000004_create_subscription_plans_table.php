<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_monthly_tzs')->default(0);
            $table->unsignedBigInteger('price_annual_tzs')->default(0);
            $table->unsignedInteger('max_facilities')->default(1);
            $table->unsignedInteger('max_users')->default(15);
            $table->unsignedInteger('storage_quota_mb')->default(5120);
            $table->jsonb('feature_flags')->default(json_encode([]));
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed Default 2027 Enterprise Hospital SaaS Plans
        DB::table('subscription_plans')->insert([
            [
                'id' => (string) Str::uuid(),
                'name' => 'Starter Tier (Clinics & Dispensaries)',
                'code' => 'starter',
                'description' => 'Tailored for outpatient clinics, polyclinics, and community dispensaries.',
                'price_monthly_tzs' => 1200000,
                'price_annual_tzs' => 12000000,
                'max_facilities' => 1,
                'max_users' => 15,
                'storage_quota_mb' => 5120,
                'feature_flags' => json_encode(['billing', 'pharmacy', 'laboratory']),
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Growth Tier (District & Regional Hospitals)',
                'code' => 'growth',
                'description' => 'Comprehensive multi-facility suite with inpatient wards, census, insurance, and payments.',
                'price_monthly_tzs' => 3500000,
                'price_annual_tzs' => 35000000,
                'max_facilities' => 5,
                'max_users' => 75,
                'storage_quota_mb' => 20480,
                'feature_flags' => json_encode(['billing', 'pharmacy', 'laboratory', 'inpatient', 'insurance', 'mpesa', 'sms']),
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Enterprise Tier (National Referral Networks)',
                'code' => 'enterprise',
                'description' => 'Unlimited capacity with Operating Theatres, DICOM PACS, HL7 FHIR R4, and MoH MTUHA BI.',
                'price_monthly_tzs' => 8500000,
                'price_annual_tzs' => 85000000,
                'max_facilities' => 50,
                'max_users' => 500,
                'storage_quota_mb' => 102400,
                'feature_flags' => json_encode(['billing', 'pharmacy', 'laboratory', 'inpatient', 'theatre', 'insurance', 'radiology', 'dicom', 'fhir', 'mpesa', 'sms', 'bi_analytics']),
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
