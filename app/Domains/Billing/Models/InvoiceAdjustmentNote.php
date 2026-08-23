<?php

namespace App\Domains\Billing\Models;

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
 * @property string $facility_id
 * @property string $invoice_id
 * @property string $type
 * @property numeric $amount
 * @property string $reason
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceAdjustmentNote whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InvoiceAdjustmentNote extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'BILLING';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
