<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property bool $is_dispensing_enabled
 * @property bool $is_storage_only
 * @property bool $is_active
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Facility $facility
 * @property-read Collection<int, GoodsReceiptNote> $goodsReceiptNotes
 * @property-read int|null $goods_receipt_notes_count
 * @property-read Collection<int, StockTransfer> $incomingTransfers
 * @property-read int|null $incoming_transfers_count
 * @property-read Collection<int, StockTransfer> $outgoingTransfers
 * @property-read int|null $outgoing_transfers_count
 * @property-read Collection<int, InventoryStockBalance> $stockBalances
 * @property-read int|null $stock_balances_count
 * @property-read Collection<int, StocktakeSession> $stocktakeSessions
 * @property-read int|null $stocktake_sessions_count
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereIsDispensingEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereIsStorageOnly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryLocation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InventoryLocation extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'is_dispensing_enabled' => 'boolean',
        'is_storage_only' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasMany<InventoryStockBalance, $this>
     */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(InventoryStockBalance::class, 'location_id');
    }

    /**
     * @return HasMany<StockTransfer, $this>
     */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'source_location_id');
    }

    /**
     * @return HasMany<StockTransfer, $this>
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'destination_location_id');
    }

    /**
     * @return HasMany<GoodsReceiptNote, $this>
     */
    public function goodsReceiptNotes(): HasMany
    {
        return $this->hasMany(GoodsReceiptNote::class, 'location_id');
    }

    /**
     * @return HasMany<StocktakeSession, $this>
     */
    public function stocktakeSessions(): HasMany
    {
        return $this->hasMany(StocktakeSession::class, 'location_id');
    }
}
