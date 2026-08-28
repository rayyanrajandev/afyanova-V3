<?php

namespace App\Domains\Billing\Http\Controllers;

use App\Domains\Billing\Actions\RecordPaymentAction;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\MpesaDarajaService;
use App\Domains\Identity\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MpesaPaymentController extends Controller
{
    public function initiateStkPush(Request $request, Invoice $invoice, MpesaDarajaService $mpesaService, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'billing.payment.collect') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'phone_number' => 'required|string|min:9|max:15',
            'amount' => 'required|numeric|min:1',
        ]);

        $result = $mpesaService->initiateStkPush($invoice, $validated['phone_number'], $validated['amount']);

        if ($result['success'] ?? false) {
            return back()->with('success', $result['CustomerMessage'] ?? 'M-Pesa STK Push sent to patient phone.');
        }

        return back()->withErrors(['mpesa' => $result['ResponseDescription'] ?? 'Failed to initiate M-Pesa prompt.']);
    }

    public function handleCallback(Request $request, MpesaDarajaService $mpesaService, RecordPaymentAction $action): JsonResponse
    {
        $payload = $request->all();
        $result = $mpesaService->handleStkCallback($payload, $action);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Callback accepted and processed successfully.',
            'Data' => $result,
        ]);
    }

    public function handleC2bValidation(Request $request): JsonResponse
    {
        // C2B Validation hook — verify account number exists
        $billRef = $request->input('BillRefNumber');
        $invoice = Invoice::where('invoice_number', $billRef)->first();

        if (! $invoice) {
            return response()->json([
                'ResultCode' => 'C2B00011',
                'ResultDesc' => 'Invalid or unknown Hospital Invoice reference number.',
            ]);
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Validation accepted for Invoice: ' . $billRef,
        ]);
    }

    public function handleC2bConfirmation(Request $request, RecordPaymentAction $action): JsonResponse
    {
        $billRef = $request->input('BillRefNumber');
        $transId = $request->input('TransID');
        $amount = floatval($request->input('TransAmount', 0));
        $msisdn = $request->input('MSISDN');

        $invoice = Invoice::where('invoice_number', $billRef)->first();

        if ($invoice && $amount > 0) {
            $action->execute($invoice, $amount, 'Mobile_Money', "M-PESA C2B: {$transId} ({$msisdn})");
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Confirmation processed successfully.',
        ]);
    }
}
