<?php

namespace App\Domains\Pharmacy\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $facility_id
 * @property string $medication_id
 * @property string $batch_number
 * @property string|null $barcode
 * @property Carbon|null $manufacture_date
 * @property Carbon $expiry_date
 * @property int $initial_quantity
 * @property int $current_quantity
 * @property numeric $unit_cost
 * @property numeric $unit_selling_price
 * @property string|null $supplier_name
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, DispenseEventBatch> $dispenseEventBatches
 * @property-read int|null $dispense_event_batches_count
 * @property-read Facility|null $facility
 * @property-read int|null $days_to_expiry
 * @property-read bool $is_expired
 * @property-read string $stock_status
 * @property-read MedicationFormulary $medication
 * @property-read Collection<int, StockMovement> $stockMovements
 * @property-read int|null $stock_movements_count
 * @property-read Tenant $tenant
 *
 * @method static Builder<static>|InventoryBatch active()
 * @method static Builder<static>|InventoryBatch available()
 * @method static Builder<static>|InventoryBatch fefo()
 * @method static Builder<static>|InventoryBatch newModelQuery()
 * @method static Builder<static>|InventoryBatch newQuery()
 * @method static Builder<static>|InventoryBatch onlyTrashed()
 * @method static Builder<static>|InventoryBatch query()
 * @method static Builder<static>|InventoryBatch whereBarcode($value)
 * @method static Builder<static>|InventoryBatch whereBatchNumber($value)
 * @method static Builder<static>|InventoryBatch whereCreatedAt($value)
 * @method static Builder<static>|InventoryBatch whereCurrentQuantity($value)
 * @method static Builder<static>|InventoryBatch whereDeletedAt($value)
 * @method static Builder<static>|InventoryBatch whereExpiryDate($value)
 * @method static Builder<static>|InventoryBatch whereFacilityId($value)
 * @method static Builder<static>|InventoryBatch whereId($value)
 * @method static Builder<static>|InventoryBatch whereInitialQuantity($value)
 * @method static Builder<static>|InventoryBatch whereManufactureDate($value)
 * @method static Builder<static>|InventoryBatch whereMedicationId($value)
 * @method static Builder<static>|InventoryBatch whereNotes($value)
 * @method static Builder<static>|InventoryBatch whereStatus($value)
 * @method static Builder<static>|InventoryBatch whereSupplierName($value)
 * @method static Builder<static>|InventoryBatch whereTenantId($value)
 * @method static Builder<static>|InventoryBatch whereUnitCost($value)
 * @method static Builder<static>|InventoryBatch whereUnitSellingPrice($value)
 * @method static Builder<static>|InventoryBatch whereUpdatedAt($value)
 * @method static Builder<static>|InventoryBatch withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|InventoryBatch withoutTrashed()
 *
 * @mixin \Eloquent
 */
class InventoryBatch extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7, SoftDeletes;

    const AUDIT_CATEGORY = 'PHARMACY_INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'manufacture_date' => 'date',
        'expiry_date' => 'date',
        'initial_quantity' => 'integer',
        'current_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'unit_selling_price' => 'decimal:2',
    ];

    protected $appends = ['is_expired', 'days_to_expiry', 'stock_status'];

    /**
     * @return BelongsTo<MedicationFormulary, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationFormulary::class, 'medication_id');
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'batch_id')->latest('created_at');
    }

    /**
     * @return HasMany<DispenseEventBatch, $this>
     */
    public function dispenseEventBatches(): HasMany
    {
        return $this->hasMany(DispenseEventBatch::class, 'batch_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'Active')
            ->where('current_quantity', '>', 0)
            ->whereDate('expiry_date', '>', now());
    }

    public function scopeFefo(Builder $query): Builder
    {
        return $query->available()->orderBy('expiry_date', 'asc');
    }

    public function getIsExpiredAttribute(): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isPast();
    }

    public function getDaysToExpiryAttribute(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return (int) now()->diffInDays($this->expiry_date, false);
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->is_expired) {
            return 'Expired';
        }
        if ($this->current_quantity <= 0) {
            return 'Depleted';
        }
        if ($this->days_to_expiry <= 30) {
            return 'Expiring Soon';
        }

        return 'Available';
    }
}
