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
 * @property string $stock_transfer_id
 * @property string $medication_id
 * @property string $batch_id
 * @property int $quantity_requested
 * @property int $quantity_dispatched
 * @property int $quantity_received
 * @property string|null $discrepancy_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryBatch|null $batch
 * @property-read MedicationFormulary $medication
 * @property-read StockTransfer $stockTransfer
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereDiscrepancyReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereMedicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereQuantityDispatched($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereQuantityReceived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereQuantityRequested($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereStockTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransferItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class StockTransferItem extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_dispatched' => 'integer',
        'quantity_received' => 'integer',
    ];

    /**
     * @return BelongsTo<StockTransfer, $this>
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
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
