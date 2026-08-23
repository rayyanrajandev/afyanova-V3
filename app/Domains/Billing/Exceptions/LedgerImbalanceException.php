<?php

namespace App\Domains\Billing\Exceptions;

use Exception;

class LedgerImbalanceException extends Exception
{
    public static function unbalanced(float $debits, float $credits): self
    {
        return new self("Ledger transaction is unbalanced. Debits: {$debits}, Credits: {$credits}");
    }

    public static function overPayment(float $amount, float $remaining): self
    {
        return new self("Payment amount {$amount} exceeds remaining invoice balance {$remaining}.");
    }
}
