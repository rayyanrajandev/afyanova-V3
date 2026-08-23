<?php

namespace App\Domains\Insurance\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $claim_remittance_id
 * @property string $insurance_claim_id
 * @property float $claimed_amount
 * @property float $settled_amount
 * @property float $disallowed_amount
 * @property string|null $disallowance_reason_code
 * @property string|null $disallowance_remarks
 * @property string $adjudication_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InsuranceClaim $claim
 * @property-read ClaimRemittance $remittance
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereAdjudicationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereClaimRemittanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereClaimedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereDisallowanceReasonCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereDisallowanceRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereDisallowedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereInsuranceClaimId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereSettledAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClaimRemittanceItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ClaimRemittanceItem extends Model
{
    use BelongsToTenant, HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'claimed_amount' => 'float',
        'settled_amount' => 'float',
        'disallowed_amount' => 'float',
    ];

    /**
     * @return BelongsTo<ClaimRemittance, $this>
     */
    public function remittance(): BelongsTo
    {
        return $this->belongsTo(ClaimRemittance::class, 'claim_remittance_id');
    }

    /**
     * @return BelongsTo<InsuranceClaim, $this>
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'insurance_claim_id');
    }
}
