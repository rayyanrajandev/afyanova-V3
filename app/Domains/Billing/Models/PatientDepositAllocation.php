<?php

namespace App\Domains\Billing\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $deposit_id
 * @property string $invoice_id
 * @property string|null $payment_id
 * @property string $allocated_by
 * @property float $allocated_amount
 * @property Carbon $allocated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $allocator
 * @property-read PatientDeposit $deposit
 * @property-read Invoice $invoice
 * @property-read Payment|null $payment
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation whereAllocatedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation whereAllocatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation whereAllocatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation whereDepositId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDepositAllocation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PatientDepositAllocation extends Model
{
    use BelongsToTenant, HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'allocated_amount' => 'float',
        'allocated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PatientDeposit, $this>
     */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(PatientDeposit::class, 'deposit_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
