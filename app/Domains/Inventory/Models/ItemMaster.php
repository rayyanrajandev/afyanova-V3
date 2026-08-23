<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $item_code
 * @property string $name
 * @property string|null $generic_name
 * @property string $category
 * @property string|null $sub_category
 * @property string|null $base_uom_id
 * @property string|null $purchasing_uom_id
 * @property int $conversion_ratio
 * @property int $reorder_level
 * @property int $safety_stock
 * @property numeric $unit_cost_price
 * @property numeric $unit_selling_price
 * @property bool $is_billable
 * @property bool $is_cold_chain
 * @property bool $is_dda_narcotic
 * @property bool $is_active
 * @property string|null $medication_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UnitOfMeasure|null $baseUom
 * @property-read Collection<int, DdaRegisterLog> $ddaLogs
 * @property-read int|null $dda_logs_count
 * @property-read MedicationFormulary|null $medication
 * @property-read UnitOfMeasure|null $purchasingUom
 * @property-read Collection<int, InventoryStockBalance> $stockBalances
 * @property-read int|null $stock_balances_count
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereBaseUomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereConversionRatio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereGenericName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereIsBillable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereIsColdChain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereIsDdaNarcotic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereItemCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereMedicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster wherePurchasingUomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereReorderLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereSafetyStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereSubCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereUnitCostPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereUnitSellingPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ItemMaster whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ItemMaster extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'conversion_ratio' => 'integer',
        'reorder_level' => 'integer',
        'safety_stock' => 'integer',
        'unit_cost_price' => 'decimal:2',
        'unit_selling_price' => 'decimal:2',
        'is_billable' => 'boolean',
        'is_cold_chain' => 'boolean',
        'is_dda_narcotic' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    /**
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function purchasingUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchasing_uom_id');
    }

    /**
     * @return BelongsTo<MedicationFormulary, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationFormulary::class, 'medication_id');
    }

    /**
     * @return HasMany<InventoryStockBalance, $this>
     */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(InventoryStockBalance::class, 'medication_id'); // maps to item
    }

    /**
     * @return HasMany<DdaRegisterLog, $this>
     */
    public function ddaLogs(): HasMany
    {
        return $this->hasMany(DdaRegisterLog::class, 'item_id');
    }
}
