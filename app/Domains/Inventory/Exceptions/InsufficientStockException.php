<?php

namespace App\Domains\Inventory\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public static function forBatch(string $medicationName, string $batchNumber, int $requested, int $available): self
    {
        return new self("Insufficient stock for {$medicationName} (Batch: {$batchNumber}). Requested {$requested}, but only {$available} available.");
    }

    public static function forLocation(string $medicationName, string $locationName, int $requested, int $available): self
    {
        return new self("Insufficient stock for {$medicationName} at location '{$locationName}'. Requested {$requested}, but only {$available} available on hand.");
    }
}
