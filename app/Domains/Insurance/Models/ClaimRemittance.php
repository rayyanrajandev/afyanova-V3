<?php

namespace App\Domains\Insurance\Models;

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
 * @property string $facility_id
 * @property string $insurance_provider_id
 * @property string $processed_by
 * @property string $remittance_number
 * @property string $payment_reference
 * @property float $total_claimed_amount
 * @property float $total_settled_amount
 * @property float $total_disallowed_amount
 * @property string $status
 * @property Carbon $remittance_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Facility $facility
 * @property-read Collection<int, ClaimRemittanceItem> $items
 * @property-read int|null $items_count
 * @property-read User $processor
 * @property-read InsuranceProvider $provider
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereInsuranceProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance wherePaymentReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereProcessedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereRemittanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereRemittanceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereTotalClaimedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereTotalDisallowedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereTotalSettledAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittance whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ClaimRemittance extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INSURANCE';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'total_claimed_amount' => 'float',
        'total_settled_amount' => 'float',
        'total_disallowed_amount' => 'float',
        'remittance_date' => 'date',
    ];

    /**
     * @return BelongsTo<InsuranceProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id');
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return HasMany<ClaimRemittanceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ClaimRemittanceItem::class);
    }
}
