<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\DepartmentRequisition;
use Illuminate\Support\Facades\DB;

class ApproveDepartmentRequisitionAction
{
    /**
     * Matron or HOD approves and authorizes the department store indent.
     */
    public function execute(
        string $requisitionId,
        ?array $approvedQuantities = null, // optional map of [itemId => approved_qty]
        ?string $userId = null,
        ?string $notes = null
    ): DepartmentRequisition {
        return DB::transaction(function () use ($requisitionId, $approvedQuantities, $userId, $notes) {
            $requisition = DepartmentRequisition::with('items')->findOrFail($requisitionId);

            if ($requisition->status !== 'Submitted') {
                throw new \InvalidArgumentException("Requisition {$requisition->requisition_number} cannot be approved in its current state ({$requisition->status}).");
            }

            if ($approvedQuantities) {
                foreach ($requisition->items as $item) {
                    if (isset($approvedQuantities[$item->id])) {
                        $item->update([
                            'quantity_approved' => (int) $approvedQuantities[$item->id],
                        ]);
                    }
                }
            }

            $requisition->update([
                'status' => 'Approved',
                'approved_by' => $userId ?? auth()->id(),
                'approved_at' => now(),
                'notes' => $notes ? ($requisition->notes."\nApproval Note: ".$notes) : $requisition->notes,
            ]);

            return $requisition->fresh(['department', 'sourceLocation', 'destinationLocation', 'items.item', 'approvedBy']);
        });
    }
}
