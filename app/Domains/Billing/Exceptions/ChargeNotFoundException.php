<?php

namespace App\Domains\Billing\Exceptions;

use Exception;

class ChargeNotFoundException extends Exception
{
    public static function forCode(string $code): self
    {
        return new self("No active charge master price found for code '{$code}' as of today. Add it to the charge master or pass an explicit price.");
    }
}
