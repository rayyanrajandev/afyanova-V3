<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $department_requisition_id
 * @property string $item_id
 * @property string|null $batch_id
 * @property int $quantity_requested
 * @property int $quantity_approved
 * @property int $quantity_dispatched
 * @property int $quantity_received
 * @property string|null $discrepancy_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryBatch|null $batch
 * @property-read ItemMaster $item
 * @property-read DepartmentRequisition $requisition
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereDepartmentRequisitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereDiscrepancyReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereQuantityApproved($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereQuantityDispatched($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereQuantityReceived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereQuantityRequested($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisitionItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class DepartmentRequisitionItem extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_approved' => 'integer',
        'quantity_dispatched' => 'integer',
        'quantity_received' => 'integer',
    ];

    /**
     * @return BelongsTo<DepartmentRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(DepartmentRequisition::class, 'department_requisition_id');
    }

    /**
     * @return BelongsTo<ItemMaster, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }

    /**
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }
}
