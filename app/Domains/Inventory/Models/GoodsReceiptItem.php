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
 * @property string $goods_receipt_note_id
 * @property string|null $purchase_order_item_id
 * @property string $medication_id
 * @property string|null $batch_id
 * @property string $batch_number
 * @property Carbon $expiry_date
 * @property int $received_quantity
 * @property int $rejected_quantity
 * @property numeric $unit_purchase_cost
 * @property numeric $unit_selling_price
 * @property numeric $total_cost
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryBatch|null $batch
 * @property-read GoodsReceiptNote $goodsReceiptNote
 * @property-read MedicationFormulary $medication
 * @property-read PurchaseOrderItem|null $purchaseOrderItem
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereBatchNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereGoodsReceiptNoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereMedicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem wherePurchaseOrderItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereReceivedQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereRejectedQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereTotalCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereUnitPurchaseCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereUnitSellingPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class GoodsReceiptItem extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'expiry_date' => 'date',
        'received_quantity' => 'integer',
        'rejected_quantity' => 'integer',
        'unit_purchase_cost' => 'decimal:2',
        'unit_selling_price' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<GoodsReceiptNote, $this>
     */
    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
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
