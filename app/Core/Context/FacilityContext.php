<?php

namespace App\Core\Context;

class FacilityContext
{
    protected ?string $facilityId = null;

    public function setFacilityId(string $facilityId): void
    {
        $this->facilityId = $facilityId;
    }

    public function getFacilityId(): ?string
    {
        return $this->facilityId;
    }

    public function hasFacility(): bool
    {
        return $this->facilityId !== null;
    }
}
