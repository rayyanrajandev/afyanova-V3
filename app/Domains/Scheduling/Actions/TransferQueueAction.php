<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Models\QueueTicket;

class TransferQueueAction
{
    public function execute(QueueTicket $ticket, string $nextServicePoint): QueueTicket
    {
        $ticket->update([
            'current_service_point' => $nextServicePoint,
            'status' => 'Waiting',
            'joined_queue_at' => now(), // Reset timer for the new department
            'called_at' => null,
        ]);

        return $ticket;
    }
}
