<?php

namespace App\Domains\Communication\Jobs;

use App\Domains\Communication\Actions\SendPatientSmsNotificationAction;
use App\Domains\Patient\Models\Patient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $phone,
        public string $message,
        public ?Patient $patient = null,
        public ?string $recipientName = null
    ) {}

    public function handle(SendPatientSmsNotificationAction $action): void
    {
        $action->execute(
            $this->phone,
            $this->message,
            $this->patient,
            $this->recipientName
        );
    }
}
