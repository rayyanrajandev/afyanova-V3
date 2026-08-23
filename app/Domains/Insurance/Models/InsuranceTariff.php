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
 * @property string $insurance_provider_id
 * @property string|null $insurance_scheme_id
 * @property string $item_type
 * @property string $item_code
 * @property string $item_name
 * @property numeric $tariff_price
 * @property bool $is_covered
 * @property bool $requires_prior_approval
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InsuranceProvider $provider
 * @property-read InsuranceScheme|null $scheme
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereInsuranceProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereInsuranceSchemeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereIsCovered($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereItemCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereItemName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereItemType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereRequiresPriorApproval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereTariffPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceTariff whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InsuranceTariff extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INSURANCE';

    protected $guarded = ['id'];

    protected $casts = [
        'tariff_price' => 'decimal:2',
        'is_covered' => 'boolean',
        'requires_prior_approval' => 'boolean',
    ];

    /**
     * @return BelongsTo<InsuranceProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id');
    }

    /**
     * @return BelongsTo<InsuranceScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(InsuranceScheme::class, 'insurance_scheme_id');
    }
}
