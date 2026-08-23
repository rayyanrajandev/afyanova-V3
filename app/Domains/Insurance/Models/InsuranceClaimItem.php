<?php

namespace App\Domains\Insurance\Models;

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
 * @property string $insurance_claim_id
 * @property string $item_type
 * @property string|null $item_code
 * @property string $description
 * @property int $quantity
 * @property numeric $unit_price
 * @property numeric $claimed_amount
 * @property numeric $approved_amount
 * @property string $status
 * @property string|null $disallowance_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InsuranceClaim $claim
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereApprovedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereClaimedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereDisallowanceReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereInsuranceClaimId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereItemCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereItemType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaimItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InsuranceClaimItem extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INSURANCE';

    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'claimed_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<InsuranceClaim, $this>
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'insurance_claim_id');
    }
}
