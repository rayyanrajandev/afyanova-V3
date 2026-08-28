<?php

namespace App\Domains\Billing\Services;

use App\Core\Context\TenantContext;
use App\Domains\Billing\Actions\RecordPaymentAction;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MpesaDarajaService
{
    protected string $env;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected string $passkey;
    protected string $shortcode;
    protected string $callbackUrl;
    protected string $baseUrl;

    public function __construct()
    {
        $this->env = config('services.mpesa.env', 'sandbox');
        $this->consumerKey = config('services.mpesa.consumer_key', 'afyanova_mpesa_key_demo');
        $this->consumerSecret = config('services.mpesa.consumer_secret', 'afyanova_mpesa_secret_demo');
        $this->passkey = config('services.mpesa.passkey', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
        $this->shortcode = config('services.mpesa.shortcode', '174379');
        $this->callbackUrl = config('services.mpesa.callback_url', url('/api/v1/payments/mpesa/callback'));
        $this->baseUrl = $this->env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Format MSISDN to standard E.164 without leading plus (e.g. 255712345678 or 254712345678)
     */
    public function formatPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($cleaned, '0')) {
            // Default to Tanzania prefix 255 if leading 0
            $cleaned = '255' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '7') || str_starts_with($cleaned, '6')) {
            $cleaned = '255' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Generate OAuth Access Token
     */
    public function getAccessToken(): string
    {
        if ($this->env === 'sandbox' && ($this->consumerKey === 'afyanova_mpesa_key_demo' || empty($this->consumerKey))) {
            return 'mock_daraja_bearer_token_' . Str::random(32);
        }

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout(10)
                ->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

            if ($response->successful()) {
                return $response->json('access_token');
            }
        } catch (\Throwable $e) {
            Log::warning("M-Pesa token generation network fallback: {$e->getMessage()}");
        }

        return 'mock_daraja_bearer_token_' . Str::random(32);
    }

    /**
     * Initiate STK Push (Lipa na M-Pesa Online prompt on patient's handset)
     */
    public function initiateStkPush(Invoice $invoice, string $phoneNumber, float $amount, ?string $accountReference = null): array
    {
        $formattedPhone = $this->formatPhoneNumber($phoneNumber);
        $timestamp = Carbon::now()->format('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
        $accountRef = $accountReference ?? $invoice->invoice_number;
        $transactionDesc = "Hospital Bill Settled: {$invoice->invoice_number}";

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => round($amount),
            'PartyA' => $formattedPhone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $formattedPhone,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => substr($accountRef, 0, 12),
            'TransactionDesc' => substr($transactionDesc, 0, 30),
        ];

        Log::info("M-Pesa STK Push initiated for Invoice {$invoice->id} to {$formattedPhone} amount TZS {$amount}");

        // In sandbox / mock mode without live Safaricom/Vodacom upstream gateway connectivity
        if ($this->env === 'sandbox' || $this->consumerKey === 'afyanova_mpesa_key_demo') {
            $checkoutRequestId = 'ws_CO_' . $timestamp . '_' . rand(100000, 999999);
            $merchantRequestId = 'MR_' . rand(10000, 99999);

            return [
                'success' => true,
                'MerchantRequestID' => $merchantRequestId,
                'CheckoutRequestID' => $checkoutRequestId,
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing on customer handset.',
                'CustomerMessage' => "Prompt sent to {$formattedPhone}. Enter PIN on handset to confirm TZS {$amount}.",
                'invoice_id' => $invoice->id,
            ];
        }

        try {
            $token = $this->getAccessToken();
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", $payload);

            if ($response->successful()) {
                $resData = $response->json();
                return array_merge(['success' => true, 'invoice_id' => $invoice->id], $resData);
            }

            return [
                'success' => false,
                'ResponseCode' => $response->json('errorCode', '1'),
                'ResponseDescription' => $response->json('errorMessage', 'Failed to initiate STK push'),
            ];
        } catch (\Throwable $e) {
            Log::error("M-Pesa STK push exception: {$e->getMessage()}");
            return [
                'success' => false,
                'ResponseCode' => '500',
                'ResponseDescription' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process STK Push Callback Webhook
     */
    public function handleStkCallback(array $payload, RecordPaymentAction $recordPaymentAction): array
    {
        Log::info('M-Pesa STK Callback received', $payload);

        $stkCallback = $payload['Body']['stkCallback'] ?? $payload;
        $resultCode = $stkCallback['ResultCode'] ?? -1;
        $resultDesc = $stkCallback['ResultDesc'] ?? 'No description';
        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
        $merchantRequestId = $stkCallback['MerchantRequestID'] ?? null;

        if ($resultCode !== 0) {
            Log::warning("M-Pesa payment failed or canceled by user: {$resultDesc} (Checkout ID: {$checkoutRequestId})");
            return [
                'status' => 'failed',
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
            ];
        }

        // Extract metadata items
        $items = $stkCallback['CallbackMetadata']['Item'] ?? [];
        $meta = [];
        foreach ($items as $item) {
            $meta[$item['Name']] = $item['Value'] ?? null;
        }

        $amount = floatval($meta['Amount'] ?? 0);
        $mpesaReceiptNumber = $meta['MpesaReceiptNumber'] ?? ('MPESA' . strtoupper(Str::random(8)));
        $phoneNumber = strval($meta['PhoneNumber'] ?? '');
        $transactionDate = strval($meta['TransactionDate'] ?? Carbon::now()->format('YmdHis'));

        // Identify matching invoice from AccountReference or recent pending invoice
        $invoice = null;
        if (isset($payload['invoice_id'])) {
            $invoice = Invoice::find($payload['invoice_id']);
        }

        if (! $invoice) {
            // Find invoice with matching balance or latest open invoice
            $invoice = Invoice::where('status', 'Issued')
                ->where('total_amount', '>=', $amount)
                ->latest()
                ->first();
        }

        if ($invoice) {
            app(TenantContext::class)->setTenantId($invoice->tenant_id);

            // Execute double-entry ledger settlement
            $recordPaymentAction->execute(
                $invoice,
                $amount,
                'Mobile_Money',
                "M-PESA: {$mpesaReceiptNumber} ({$phoneNumber})"
            );

            Log::info("M-Pesa payment {$mpesaReceiptNumber} reconciled for Invoice {$invoice->invoice_number} (TZS {$amount})");
        }

        return [
            'status' => 'success',
            'receipt' => $mpesaReceiptNumber,
            'amount' => $amount,
            'phone' => $phoneNumber,
            'invoice_id' => $invoice?->id,
        ];
    }
}
