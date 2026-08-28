<?php

namespace App\Domains\Communication\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Communication\Models\SmsNotificationLog;
use App\Domains\Communication\Services\SmsGatewayService;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendPatientSmsNotificationAction
{
    public function __construct(
        protected SmsGatewayService $gateway
    ) {}

    public function execute(
        string $phone,
        string $message,
        ?Patient $patient = null,
        ?string $recipientName = null
    ): SmsNotificationLog {
        $tenantId = app(TenantContext::class)->getTenantId() ?? $patient?->tenant_id ?? Tenant::first()?->id;
        $facilityId = app(FacilityContext::class)->getFacilityId() ?? $patient?->facility_id;
        $name = $recipientName ?? ($patient ? ($patient->first_name . ' ' . $patient->last_name) : 'Patient');

        $result = $this->gateway->sendSms($phone, $message, $name);

        $log = SmsNotificationLog::create([
            'tenant_id' => $tenantId,
            'facility_id' => $facilityId,
            'patient_id' => $patient?->id,
            'recipient_phone' => $this->gateway->formatPhoneNumber($phone),
            'recipient_name' => $name,
            'message_body' => $message,
            'gateway_provider' => $result['provider'] ?? 'Beem',
            'message_id' => $result['message_id'] ?? null,
            'status' => $result['status'] ?? 'Sent',
            'cost_credits' => $result['cost_credits'] ?? 1.0,
            'delivery_timestamp' => ($result['status'] ?? '') === 'Delivered' ? Carbon::now() : null,
            'error_message' => $result['error'] ?? null,
        ]);

        Log::info("SMS logged with ID {$log->id} for {$phone}");

        return $log;
    }
}
