<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
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
 * @property string $grn_number
 * @property string|null $purchase_order_id
 * @property string $supplier_id
 * @property string $facility_id
 * @property string $location_id
 * @property string|null $supplier_invoice_number
 * @property string|null $delivery_note_number
 * @property Carbon $received_date
 * @property string $status
 * @property numeric $total_received_value
 * @property string|null $received_by
 * @property string|null $verified_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Facility $facility
 * @property-read Collection<int, GoodsReceiptItem> $items
 * @property-read int|null $items_count
 * @property-read InventoryLocation $location
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read User|null $receivedBy
 * @property-read Supplier $supplier
 * @property-read Tenant|null $tenant
 * @property-read User|null $verifiedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereDeliveryNoteNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereGrnNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote wherePurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereReceivedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereReceivedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereSupplierInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereTotalReceivedValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptNote whereVerifiedBy($value)
 *
 * @mixin \Eloquent
 */
class GoodsReceiptNote extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'received_date' => 'date',
        'total_received_value' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

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
     * @return HasMany<GoodsReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
