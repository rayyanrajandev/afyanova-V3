<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $transfer_number
 * @property string $source_location_id
 * @property string $destination_location_id
 * @property string $status
 * @property string|null $dispatched_by
 * @property string|null $received_by
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $received_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryLocation $destinationLocation
 * @property-read User|null $dispatchedBy
 * @property-read Collection<int, StockTransferItem> $items
 * @property-read int|null $items_count
 * @property-read User|null $receivedBy
 * @property-read InventoryLocation $sourceLocation
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereDestinationLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereDispatchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereDispatchedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereReceivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereReceivedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereSourceLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereTransferNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class StockTransfer extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }

    /**
     * @return HasMany<StockTransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
