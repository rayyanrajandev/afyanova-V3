<?php

namespace App\Domains\Scheduling\Exceptions;

use Exception;

class SchedulingConflictException extends Exception
{
    public static function doubleBooking(string $providerId, string $time): self
    {
        return new self("The provider {$providerId} is already booked at {$time}.");
    }

    public static function outsideSchedule(string $providerId, string $time): self
    {
        return new self("The time {$time} is outside the working schedule for provider {$providerId}.");
    }
}
