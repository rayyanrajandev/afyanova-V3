<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $stocktake_session_id
 * @property string $medication_id
 * @property string|null $batch_id
 * @property int $system_expected_quantity
 * @property int $physical_counted_quantity
 * @property int $variance_quantity
 * @property numeric $variance_value_tzs
 * @property string|null $variance_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryBatch|null $batch
 * @property-read MedicationFormulary $medication
 * @property-read StocktakeSession $stocktakeSession
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereMedicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem wherePhysicalCountedQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereStocktakeSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereSystemExpectedQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereVarianceQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereVarianceReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeItem whereVarianceValueTzs($value)
 *
 * @mixin \Eloquent
 */
class StocktakeItem extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'system_expected_quantity' => 'integer',
        'physical_counted_quantity' => 'integer',
        'variance_quantity' => 'integer',
        'variance_value_tzs' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<StocktakeSession, $this>
     */
    public function stocktakeSession(): BelongsTo
    {
        return $this->belongsTo(StocktakeSession::class);
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
