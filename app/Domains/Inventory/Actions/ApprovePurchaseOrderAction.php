<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\PurchaseOrder;

class ApprovePurchaseOrderAction
{
    public function execute(string $purchaseOrderId, ?string $userId = null): PurchaseOrder
    {
        $po = PurchaseOrder::findOrFail($purchaseOrderId);

        $po->update([
            'status' => 'Approved',
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => now(),
        ]);

        return $po->fresh(['supplier', 'facility', 'items.medication']);
    }
}
