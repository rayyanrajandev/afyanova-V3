<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('facility_id')->nullable()->index();
            $table->uuid('patient_id')->nullable()->index();
            $table->string('recipient_phone', 30);
            $table->string('recipient_name', 150)->nullable();
            $table->text('message_body');
            $table->string('gateway_provider', 50)->default('Beem');
            $table->string('message_id', 100)->nullable()->index();
            $table->string('status', 30)->default('Queued')->index(); // Queued, Sent, Delivered, Failed
            $table->decimal('cost_credits', 8, 2)->default(1.00);
            $table->timestamp('delivery_timestamp')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_notification_logs');
    }
};
