<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string $location_id
 * @property string $medication_id
 * @property string|null $batch_id
 * @property int $quantity_on_hand
 * @property int $quantity_reserved
 * @property int $reorder_level
 * @property int $reorder_quantity
 * @property Carbon|null $last_counted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryBatch|null $batch
 * @property-read Facility $facility
 * @property-read InventoryLocation $location
 * @property-read MedicationFormulary $medication
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereLastCountedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereMedicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereQuantityOnHand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereQuantityReserved($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereReorderLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereReorderQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockBalance whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InventoryStockBalance extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity_on_hand' => 'integer',
        'quantity_reserved' => 'integer',
        'reorder_level' => 'integer',
        'reorder_quantity' => 'integer',
        'last_counted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * @return BelongsTo<MedicationFormulary, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationFormulary::class, 'medication_id');
    }

    /**
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }
}
