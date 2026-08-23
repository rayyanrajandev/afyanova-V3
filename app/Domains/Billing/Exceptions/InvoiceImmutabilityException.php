<?php

namespace App\Domains\Billing\Exceptions;

use Exception;

class InvoiceImmutabilityException extends Exception
{
    public static function totalCannotBeMutatedOnceLocked(string $invoiceId, string $status): self
    {
        return new self("Invoice {$invoiceId} is '{$status}' and its total is locked. Issue a credit or debit note instead of mutating it directly.");
    }

    public static function cannotAddLineItemOnceLocked(string $invoiceId, string $status): self
    {
        return new self("Invoice {$invoiceId} is '{$status}' and no longer accepts new line items. Issue a credit or debit note instead.");
    }

    public static function cannotIssueFromStatus(string $invoiceId, string $status): self
    {
        return new self("Invoice {$invoiceId} cannot be issued from status '{$status}'.");
    }
}
