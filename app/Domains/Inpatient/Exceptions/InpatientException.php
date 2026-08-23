<?php

namespace App\Domains\Inpatient\Exceptions;

use Exception;

class InpatientException extends Exception
{
    public static function bedNotAvailable(string $bedNumber, string $currentStatus): self
    {
        return new self("Cannot admit patient to {$bedNumber}. Bed is currently {$currentStatus}.");
    }

    public static function patientAlreadyAdmitted(string $patientName): self
    {
        return new self("Patient {$patientName} is already admitted in an active inpatient ward.");
    }

    public static function admissionAlreadyDischarged(): self
    {
        return new self('This inpatient admission is already closed and discharged.');
    }

    public static function destinationBedOccupied(string $bedNumber): self
    {
        return new self("Cannot transfer patient. Destination bed {$bedNumber} is already occupied.");
    }

    public static function patientDeceased(string $patientName): self
    {
        return new self("Cannot admit patient {$patientName}. Patient record is marked as Deceased.");
    }
}
