<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Exceptions\InvoiceImmutabilityException;
use App\Domains\Billing\Models\Invoice;

/**
 * Locks an invoice's total at cashier checkout. Before this runs, charges
 * accumulate freely on the invoice as the encounter progresses (status
 * 'Open'); after it runs, the total is locked and any correction must go
 * through IssueInvoiceAdjustmentAction.
 */
class IssueInvoiceAction
{
    public function execute(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, ['Open', 'Draft'], true)) {
            throw InvoiceImmutabilityException::cannotIssueFromStatus($invoice->id, $invoice->status);
        }

        $invoice->update([
            'status' => 'Issued',
            'issued_at' => now(),
        ]);

        return $invoice->refresh();
    }
}
