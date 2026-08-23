<?php

namespace App\Domains\Billing\Http\Controllers;

use App\Domains\Billing\Actions\CloseCashierShiftAction;
use App\Domains\Billing\Actions\GenerateInvoiceAction;
use App\Domains\Billing\Actions\IssueInvoiceAction;
use App\Domains\Billing\Actions\IssueInvoiceAdjustmentAction;
use App\Domains\Billing\Actions\IssueRefundAction;
use App\Domains\Billing\Actions\OpenCashierShiftAction;
use App\Domains\Billing\Actions\RecordPaymentAction;
use App\Domains\Billing\Exceptions\InvoiceImmutabilityException;
use App\Domains\Billing\Exceptions\LedgerImbalanceException;
use App\Domains\Billing\Models\CashierShift;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\LedgerEntry;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $invoices = Invoice::with(['patient', 'lineItems'])
            ->orderBy('created_at', 'desc')
            ->get();

        $user = auth()->user() ?? User::first();

        $activeShift = CashierShift::with(['user', 'facility'])
            ->where('user_id', $user?->id)
            ->where('status', 'Open')
            ->first();

        $recentShifts = CashierShift::with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Calculate live till telemetry for the active shift
        $telemetry = [
            'cash_in_drawer' => (float) ($activeShift?->opening_float ?? 0.00),
            'opening_float' => (float) ($activeShift?->opening_float ?? 0.00),
            'lipa_namba_total' => 0.00,
            'card_pos_total' => 0.00,
            'nhif_total' => 0.00,
            'cash_collected' => 0.00,
            'total_inflow' => 0.00,
            'invoices_settled' => 0,
        ];

        if ($activeShift) {
            $entries = LedgerEntry::whereHas('transaction', function ($q) use ($activeShift) {
                $q->where('user_id', $activeShift->user_id)
                    ->where('created_at', '>=', $activeShift->opened_at);
            })->with(['account', 'transaction'])->get();

            foreach ($entries as $entry) {
                $code = $entry->account?->code;
                if ($code === '1000') {
                    $telemetry['cash_collected'] += ((float) $entry->debit - (float) $entry->credit);
                } elseif ($code === '1020') {
                    $telemetry['lipa_namba_total'] += (float) $entry->debit;
                } elseif ($code === '1030') {
                    $telemetry['card_pos_total'] += (float) $entry->debit;
                } elseif ($code === '1200') {
                    $telemetry['nhif_total'] += (float) $entry->debit;
                }
            }

            $telemetry['cash_in_drawer'] = (float) $activeShift->opening_float + $telemetry['cash_collected'];
            $telemetry['total_inflow'] = $telemetry['cash_collected'] + $telemetry['lipa_namba_total'] + $telemetry['card_pos_total'] + $telemetry['nhif_total'];
            $telemetry['invoices_settled'] = $entries->pluck('transaction.reference_id')->filter()->unique()->count();
        }

        return Inertia::render('Workspace/BillingWorkspace', [
            'invoices' => $invoices,
            'activeShift' => $activeShift,
            'recentShifts' => $recentShifts,
            'tillTelemetry' => $telemetry,
        ]);
    }

    public function generate(Encounter $encounter, GenerateInvoiceAction $action)
    {
        $this->authorize('create', [Invoice::class, $encounter->facility_id]);

        $action->execute($encounter);

        return back()->with('success', 'Invoice generated successfully.');
    }

    public function pay(Request $request, Invoice $invoice, RecordPaymentAction $action)
    {
        $this->authorize('collectPayment', $invoice);

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:1',
            'payment_method' => 'nullable|string',
            'reference_number' => 'nullable|string|max:100',
            'splits' => 'nullable|array',
            'splits.*.amount' => 'required_with:splits|numeric|min:1',
            'splits.*.payment_method' => 'required_with:splits|string',
            'splits.*.reference_number' => 'nullable|string|max:100',
        ]);

        try {
            if (! empty($validated['splits'])) {
                foreach ($validated['splits'] as $split) {
                    $action->execute(
                        $invoice->fresh(),
                        $split['amount'],
                        $split['payment_method'],
                        $split['reference_number'] ?? null
                    );
                }
            } else {
                $action->execute(
                    $invoice,
                    $validated['amount'],
                    $validated['payment_method'] ?? 'Cash',
                    $validated['reference_number'] ?? null
                );
            }

            return back()->with('success', 'Payment recorded successfully to ledger.');
        } catch (LedgerImbalanceException $e) {
            return back()->withErrors(['billing' => $e->getMessage()]);
        }
    }

    public function refund(Request $request, Invoice $invoice, IssueRefundAction $action)
    {
        $this->authorize('refund', $invoice);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string',
        ]);

        try {
            $action->execute($invoice, $validated['amount'], $validated['reason']);

            return back()->with('success', 'Refund issued and ledger reversed.');
        } catch (LedgerImbalanceException $e) {
            return back()->withErrors(['billing' => $e->getMessage()]);
        }
    }

    public function addItem(Request $request, Invoice $invoice)
    {
        $this->authorize('addItem', $invoice);

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'category' => 'required|string|in:Consultation,Pharmacy,Lab,Procedure,Nursing',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($invoice, $validated) {
            $totalPrice = $validated['quantity'] * $validated['unit_price'];

            $invoice->lineItems()->create([
                'tenant_id' => $invoice->tenant_id,
                'description' => $validated['description'],
                'category' => $validated['category'],
                'quantity' => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'total_price' => $totalPrice,
            ]);

            $newTotal = $invoice->lineItems()->sum('total_price');
            $status = ($invoice->paid_amount >= $newTotal && $newTotal > 0)
                ? 'Paid'
                : ($invoice->paid_amount > 0 ? 'Partially Paid' : 'Issued');

            $invoice->update([
                'total_amount' => $newTotal,
                'status' => $status,
            ]);
        });

        return back()->with('success', 'Charge item added to invoice.');
    }

    public function issue(Invoice $invoice, IssueInvoiceAction $action)
    {
        $this->authorize('issue', $invoice);

        try {
            $action->execute($invoice);

            return back()->with('success', 'Invoice issued and locked for cashier checkout.');
        } catch (InvoiceImmutabilityException $e) {
            return back()->withErrors(['billing' => $e->getMessage()]);
        }
    }

    public function adjustInvoice(Request $request, Invoice $invoice, IssueInvoiceAdjustmentAction $action)
    {
        $this->authorize('adjust', $invoice);

        $validated = $request->validate([
            'type' => 'required|string|in:Credit,Debit',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $action->execute($invoice, $validated['type'], (float) $validated['amount'], $validated['reason']);

            return back()->with('success', "{$validated['type']} note issued against invoice.");
        } catch (LedgerImbalanceException $e) {
            return back()->withErrors(['billing' => $e->getMessage()]);
        }
    }

    public function openShift(Request $request, OpenCashierShiftAction $action)
    {
        $validated = $request->validate([
            'opening_float' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $action->execute($validated['opening_float'], $validated['notes'] ?? null);

        return back()->with('success', 'Cashier shift session opened successfully.');
    }

    public function closeShift(Request $request, CashierShift $shift, CloseCashierShiftAction $action)
    {
        $validated = $request->validate([
            'closing_cash_counted' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $action->execute($shift, $validated['closing_cash_counted'], $validated['notes'] ?? null);

        return back()->with('success', 'Cashier shift closed and physical cash reconciled.');
    }
}
