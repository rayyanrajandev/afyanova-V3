<?php

namespace App\Domains\Patient\Events;

use App\Domains\Patient\Models\Patient;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientMergedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Patient $winner,
        public readonly Patient $loser,
        public readonly ?string $userId = null
    ) {}
}
