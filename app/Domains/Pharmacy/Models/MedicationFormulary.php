<?php

namespace App\Domains\Pharmacy\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $generic_name
 * @property string|null $brand_name
 * @property string $form
 * @property string $strength
 * @property string $route
 * @property string|null $drug_class
 * @property string|null $charge_code
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InventoryBatch> $activeBatches
 * @property-read int $active_batches_count
 * @property-read Collection<int, InventoryBatch> $batches
 * @property-read int|null $batches_count
 * @property-read string|null $earliest_expiry_date
 * @property-read string $stock_status
 * @property-read int $total_stock_on_hand
 * @property-read Collection<int, StockMovement> $stockMovements
 * @property-read int|null $stock_movements_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereBrandName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereChargeCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereDrugClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereForm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereGenericName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereRoute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereStrength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationFormulary whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MedicationFormulary extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PHARMACY_FORMULARY';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
    ];

    protected $appends = ['total_stock_on_hand', 'active_batches_count', 'earliest_expiry_date', 'stock_status'];

    /**
     * @return HasMany<InventoryBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'medication_id');
    }

    /**
     * @return HasMany<InventoryBatch, $this>
     */
    public function activeBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'medication_id')
            ->where('status', 'Active')
            ->where('current_quantity', '>', 0);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'medication_id')->latest('created_at');
    }

    public function getTotalStockOnHandAttribute(): int
    {
        if ($this->relationLoaded('batches')) {
            return (int) $this->batches
                ->filter(fn ($b) => $b->status === 'Active' && (! $b->expiry_date || ! $b->expiry_date->isPast()))
                ->sum('current_quantity');
        }

        return (int) $this->batches()
            ->where('status', 'Active')
            ->whereDate('expiry_date', '>', now())
            ->sum('current_quantity');
    }

    public function getActiveBatchesCountAttribute(): int
    {
        if ($this->relationLoaded('batches')) {
            return $this->batches->filter(fn ($b) => $b->status === 'Active' && $b->current_quantity > 0)->count();
        }

        return $this->batches()->where('status', 'Active')->where('current_quantity', '>', 0)->count();
    }

    public function getEarliestExpiryDateAttribute(): ?string
    {
        if ($this->relationLoaded('batches')) {
            $earliest = $this->batches
                ->filter(fn ($b) => $b->status === 'Active' && $b->current_quantity > 0 && $b->expiry_date)
                ->sortBy('expiry_date')
                ->first();

            return $earliest ? $earliest->expiry_date->format('Y-m-d') : null;
        }

        $earliest = $this->batches()
            ->where('status', 'Active')
            ->where('current_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date', 'asc')
            ->first();

        return $earliest ? $earliest->expiry_date->format('Y-m-d') : null;
    }

    public function getStockStatusAttribute(): string
    {
        $stock = $this->total_stock_on_hand;
        if ($stock <= 0) {
            return 'Stockout';
        }
        if ($stock <= 50) {
            return 'Low Stock';
        }

        return 'In Stock';
    }
}
