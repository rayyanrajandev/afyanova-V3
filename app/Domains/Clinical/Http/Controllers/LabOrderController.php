<?php

namespace App\Domains\Clinical\Http\Controllers;

use App\Domains\Clinical\Actions\CreateLabOrderAction;
use App\Domains\Clinical\Actions\EnterLabResultsAction;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrderItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LabOrderController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Encounter $encounter, CreateLabOrderAction $action)
    {
        $this->authorize('orderLabs', $encounter);

        $validated = $request->validate([
            'test_ids' => 'required|array|min:1',
            'test_ids.*' => 'required|string|exists:lab_tests,id',
            'priority' => 'nullable|string|in:Routine,Urgent,STAT',
            'clinical_notes' => 'nullable|string',
        ]);

        try {
            $action->execute(
                $encounter,
                $validated['test_ids'],
                $validated['priority'] ?? 'Routine',
                $validated['clinical_notes'] ?? null
            );

            return back()->with('success', 'Lab investigation order created and billed successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['lab_order' => $e->getMessage()]);
        }
    }

    public function collectSample(LabOrderItem $item)
    {
        $this->authorize('collect', $item);

        $item->update([
            'status' => 'Sample Collected',
        ]);

        $order = $item->labOrder;
        if ($order->status === 'Ordered') {
            $order->update([
                'status' => 'Sample Collected',
                'collected_at' => now(),
            ]);
        }

        return back()->with('success', 'Specimen sample marked as collected.');
    }

    public function enterResults(Request $request, LabOrderItem $item, EnterLabResultsAction $action)
    {
        $this->authorize('recordResults', $item);

        $validated = $request->validate([
            'results' => 'required|array',
            'technician_remarks' => 'nullable|string',
        ]);

        try {
            $updatedItem = $action->execute(
                $item,
                $validated['results'],
                $validated['technician_remarks'] ?? null
            );

            $msg = $updatedItem->has_critical_value
                ? 'CRITICAL PANIC VALUE DETECTED! Attending physician alert generated.'
                : 'Lab results verified and recorded successfully.';

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['lab_results' => $e->getMessage()]);
        }
    }
}
