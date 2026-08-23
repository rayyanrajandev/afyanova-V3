<?php

namespace App\Domains\Insurance\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $insurance_provider_id
 * @property string $code
 * @property string $name
 * @property string $co_pay_type
 * @property numeric $co_pay_amount
 * @property numeric|null $annual_limit_amount
 * @property bool $requires_pre_auth
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PatientPolicy> $policies
 * @property-read int|null $policies_count
 * @property-read InsuranceProvider $provider
 * @property-read Collection<int, InsuranceTariff> $tariffs
 * @property-read int|null $tariffs_count
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereAnnualLimitAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereCoPayAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereCoPayType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereInsuranceProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereRequiresPreAuth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceScheme whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InsuranceScheme extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INSURANCE';

    protected $guarded = ['id'];

    protected $casts = [
        'co_pay_amount' => 'decimal:2',
        'annual_limit_amount' => 'decimal:2',
        'requires_pre_auth' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<InsuranceProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id');
    }

    /**
     * @return HasMany<PatientPolicy, $this>
     */
    public function policies(): HasMany
    {
        return $this->hasMany(PatientPolicy::class);
    }

    /**
     * @return HasMany<InsuranceTariff, $this>
     */
    public function tariffs(): HasMany
    {
        return $this->hasMany(InsuranceTariff::class);
    }
}
