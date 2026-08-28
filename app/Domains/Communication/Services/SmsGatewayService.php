<?php

namespace App\Domains\Communication\Services;

use App\Domains\Communication\Models\SmsNotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsGatewayService
{
    protected string $provider;
    protected string $beemApiKey;
    protected string $beemSecretKey;
    protected string $beemSenderId;
    protected string $nextSmsUsername;
    protected string $nextSmsPassword;
    protected string $nextSmsSenderId;

    public function __construct()
    {
        $this->provider = config('services.sms.provider', 'beem');
        $this->beemApiKey = config('services.sms.beem.api_key', 'afyanova_beem_key_demo');
        $this->beemSecretKey = config('services.sms.beem.secret_key', 'afyanova_beem_secret_demo');
        $this->beemSenderId = config('services.sms.beem.sender_id', 'AFYANOVA');
        $this->nextSmsUsername = config('services.sms.nextsms.username', 'afyanova_demo');
        $this->nextSmsPassword = config('services.sms.nextsms.password', 'afyanova_pass_demo');
        $this->nextSmsSenderId = config('services.sms.nextsms.sender_id', 'AFYANOVA');
    }

    /**
     * Format MSISDN to 255XXXXXXXXX without leading +
     */
    public function formatPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '255' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '7') || str_starts_with($cleaned, '6')) {
            $cleaned = '255' . $cleaned;
        }
        return $cleaned;
    }

    /**
     * Send SMS via configured Gateway (Beem / NextSMS / Mock Fallback)
     */
    public function sendSms(string $recipientPhone, string $message, ?string $recipientName = null): array
    {
        $formattedPhone = $this->formatPhoneNumber($recipientPhone);

        // Mock / Sandbox mode
        if ($this->beemApiKey === 'afyanova_beem_key_demo' || empty($this->beemApiKey)) {
            Log::info("SMS [MOCK] sent to {$formattedPhone} via {$this->provider}: {$message}");
            return [
                'success' => true,
                'provider' => ucfirst($this->provider),
                'message_id' => 'SMS_' . Str::random(16),
                'status' => 'Delivered',
                'cost_credits' => 1.0,
            ];
        }

        if ($this->provider === 'nextsms') {
            return $this->sendViaNextSms($formattedPhone, $message);
        }

        return $this->sendViaBeem($formattedPhone, $message);
    }

    protected function sendViaBeem(string $phone, string $message): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode("{$this->beemApiKey}:{$this->beemSecretKey}"),
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://apisms.beem.africa/v1/send', [
                'source_addr' => $this->beemSenderId,
                'schedule_time' => '',
                'encoding' => 0,
                'message' => $message,
                'recipients' => [
                    ['recipient_id' => 1, 'dest_addr' => $phone],
                ],
            ]);

            if ($response->successful()) {
                $res = $response->json();
                return [
                    'success' => true,
                    'provider' => 'Beem',
                    'message_id' => $res['data']['message_id'] ?? 'BEEM_' . Str::random(12),
                    'status' => 'Sent',
                    'cost_credits' => 1.0,
                ];
            }

            return [
                'success' => false,
                'provider' => 'Beem',
                'error' => $response->body(),
                'status' => 'Failed',
            ];
        } catch (\Throwable $e) {
            Log::error("Beem SMS send error: {$e->getMessage()}");
            return [
                'success' => false,
                'provider' => 'Beem',
                'error' => $e->getMessage(),
                'status' => 'Failed',
            ];
        }
    }

    protected function sendViaNextSms(string $phone, string $message): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode("{$this->nextSmsUsername}:{$this->nextSmsPassword}"),
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://messaging-service.co.tz/api/sms/v1/text/single', [
                'from' => $this->nextSmsSenderId,
                'to' => $phone,
                'text' => $message,
            ]);

            if ($response->successful()) {
                $res = $response->json();
                return [
                    'success' => true,
                    'provider' => 'NextSMS',
                    'message_id' => $res['messages'][0]['messageId'] ?? 'NEXT_' . Str::random(12),
                    'status' => 'Sent',
                    'cost_credits' => 1.0,
                ];
            }

            return [
                'success' => false,
                'provider' => 'NextSMS',
                'error' => $response->body(),
                'status' => 'Failed',
            ];
        } catch (\Throwable $e) {
            Log::error("NextSMS send error: {$e->getMessage()}");
            return [
                'success' => false,
                'provider' => 'NextSMS',
                'error' => $e->getMessage(),
                'status' => 'Failed',
            ];
        }
    }
}
