<?php

namespace App\Domains\Billing\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property string $description
 * @property string $category
 * @property int $quantity
 * @property numeric $unit_price
 * @property numeric $total_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceLineItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InvoiceLineItem extends Model
{
    use BelongsToTenant, HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
