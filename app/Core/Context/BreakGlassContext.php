<?php

namespace App\Core\Context;

/**
 * Request-scoped: which patient (if any) an emergency break-glass
 * override, granted by BreakGlassController and read by
 * BreakGlassScope middleware, applies to for the current request.
 * Consumed by Patient::booted()'s facility-visibility scope.
 */
class BreakGlassContext
{
    protected ?string $patientId = null;

    public function setPatientId(string $patientId): void
    {
        $this->patientId = $patientId;
    }

    public function getPatientId(): ?string
    {
        return $this->patientId;
    }
}
