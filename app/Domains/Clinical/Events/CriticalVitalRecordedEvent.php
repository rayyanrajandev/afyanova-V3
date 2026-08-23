<?php

namespace App\Domains\Clinical\Events;

use App\Domains\Clinical\Models\ClinicalVital;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CriticalVitalRecordedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ClinicalVital $vital,
        public readonly array $panicFlags,
        public readonly ?string $recordedById = null
    ) {}
}
