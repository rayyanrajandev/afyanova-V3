<?php

namespace App\Domains\Laboratory\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\LabTest;
use Illuminate\Support\Facades\DB;

class CreateCustomLabTestAction
{
    public function execute(array $data): LabTest
    {
        return DB::transaction(function () use ($data) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? auth()->user()?->tenant_id ?? 'default';

            return LabTest::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'test_code' => $data['test_code'],
                ],
                [
                    'name' => $data['name'],
                    'category' => $data['category'] ?? 'General',
                    'specimen_type' => $data['specimen_type'] ?? 'Blood',
                    'inventory_item_id' => $data['inventory_item_id'] ?? null,
                    'turnaround_time_minutes' => intval($data['turnaround_time_minutes'] ?? 30),
                    'price' => floatval($data['price'] ?? 0.00),
                    'parameters' => $data['parameters'] ?? [],
                    'is_active' => $data['is_active'] ?? true,
                ]
            );
        });
    }
}
