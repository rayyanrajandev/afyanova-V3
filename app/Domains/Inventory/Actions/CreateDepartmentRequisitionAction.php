<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\DepartmentRequisition;
use App\Domains\Inventory\Models\DepartmentRequisitionItem;
use App\Domains\Inventory\Models\InventoryLocation;
use Illuminate\Support\Facades\DB;

class CreateDepartmentRequisitionAction
{
    /**
     * Ward nurse or HOD creates an internal store indent request.
     */
    public function execute(
        string $facilityId,
        ?string $departmentId,
        string $sourceLocationId,
        string $destinationLocationId,
        array $items, // array of ['item_id', 'quantity_requested']
        string $requisitionType = 'Routine_Weekly',
        ?string $userId = null,
        ?string $notes = null
    ): DepartmentRequisition {
        return DB::transaction(function () use (
            $facilityId, $departmentId, $sourceLocationId, $destinationLocationId, $items, $requisitionType, $userId, $notes
        ) {
            $sourceLoc = InventoryLocation::findOrFail($sourceLocationId);
            $tenantId = $sourceLoc->tenant_id;
            $reqNumber = 'REQ-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(3)));

            $requisition = DepartmentRequisition::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'department_id' => $departmentId,
                'requisition_number' => $reqNumber,
                'source_location_id' => $sourceLocationId,
                'destination_location_id' => $destinationLocationId,
                'requisition_type' => $requisitionType,
                'status' => 'Submitted',
                'requested_by' => $userId ?? auth()->id(),
                'submitted_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($items as $itemData) {
                DepartmentRequisitionItem::create([
                    'tenant_id' => $tenantId,
                    'department_requisition_id' => $requisition->id,
                    'item_id' => $itemData['item_id'],
                    'quantity_requested' => (int) $itemData['quantity_requested'],
                    'quantity_approved' => (int) $itemData['quantity_requested'],
                    'quantity_dispatched' => 0,
                    'quantity_received' => 0,
                ]);
            }

            return $requisition->load(['department', 'sourceLocation', 'destinationLocation', 'items.item', 'requestedBy']);
        });
    }
}
