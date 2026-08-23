<?php

namespace App\Domains\Pharmacy\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $dispense_event_id
 * @property string $batch_id
 * @property int $quantity_dispensed
 * @property numeric $unit_price_at_dispense
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryBatch|null $batch
 * @property-read DispenseEvent $dispenseEvent
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch whereDispenseEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch whereQuantityDispensed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch whereUnitPriceAtDispense($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEventBatch whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class DispenseEventBatch extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PHARMACY_DISPENSE_BATCH';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'quantity_dispensed' => 'integer',
        'unit_price_at_dispense' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<DispenseEvent, $this>
     */
    public function dispenseEvent(): BelongsTo
    {
        return $this->belongsTo(DispenseEvent::class);
    }

    /**
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }
}
