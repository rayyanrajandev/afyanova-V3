<?php

namespace App\Domains\Scheduling\Http\Controllers;

use App\Domains\Scheduling\Actions\TransferQueueAction;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $servicePoint = $request->query('point', 'Triage');

        $tickets = QueueTicket::with(['patient'])
            ->where('current_service_point', $servicePoint)
            ->whereIn('status', ['Waiting', 'In Progress'])
            ->orderByRaw("CASE priority WHEN 'Emergency' THEN 1 WHEN 'Urgent' THEN 2 ELSE 3 END")
            ->orderBy('joined_queue_at')
            ->get();

        return Inertia::render('Domains/Scheduling/LiveQueue', [
            'tickets' => $tickets,
            'currentPoint' => $servicePoint,
        ]);
    }

    public function transfer(Request $request, QueueTicket $ticket, TransferQueueAction $action)
    {
        $this->authorize('transfer', $ticket);

        $validated = $request->validate([
            'next_service_point' => 'required|string',
        ]);

        $action->execute($ticket, $validated['next_service_point']);

        return back()->with('success', "Patient transferred to {$validated['next_service_point']}.");
    }

    public function call(QueueTicket $ticket)
    {
        $this->authorize('call', $ticket);

        $ticket->update([
            'status' => 'In Progress',
            'called_at' => now(),
        ]);

        return redirect()->route('encounters.workspace', $ticket->encounter_id);
    }
}
