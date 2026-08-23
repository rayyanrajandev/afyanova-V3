<?php

namespace App\Domains\Pharmacy\Exceptions;

use Exception;

class PharmacyException extends Exception
{
    public static function allergyContraindication(string $drugClass): self
    {
        return new self("Cannot prescribe medication. Patient has a known allergy to: {$drugClass}.");
    }

    public static function duplicateActivePrescription(string $medicationName): self
    {
        return new self("A prescription for '{$medicationName}' is already active in this consultation.");
    }

    public static function dispenseQuantityExceeded(int $requested, int $remaining): self
    {
        return new self("Cannot dispense {$requested}. Only {$remaining} remaining on prescription.");
    }

    public static function prescriptionNotVerified(): self
    {
        return new self('Cannot dispense an unverified prescription. Pharmacist clinical verification is required.');
    }

    public static function insufficientStock(string $medicationName, int $requested, int $available): self
    {
        return new self("Insufficient inventory for '{$medicationName}'. Requested: {$requested}, Available: {$available}.");
    }

    public static function batchExpired(string $batchNumber): self
    {
        return new self("Batch '{$batchNumber}' is expired and cannot be dispensed.");
    }

    public static function missingChargeCode(string $medicationName): self
    {
        return new self("'{$medicationName}' has no charge_code configured, so no billable price can be resolved. Set one in the formulary before prescribing.");
    }

    public static function drugInteractionContraindication(string $candidateMed, string $conflictingMed, string $severity, string $effect): self
    {
        return new self("Severe Drug-Drug Interaction [{$severity}]: Prescribing '{$candidateMed}' conflicts with active medication '{$conflictingMed}'. {$effect}");
    }

    public static function deceasedPatient(string $patientName): self
    {
        return new self("Cannot prescribe medication for {$patientName}. Patient record is marked as Deceased.");
    }
}
