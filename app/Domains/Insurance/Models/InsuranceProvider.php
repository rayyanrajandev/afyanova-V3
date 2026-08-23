<?php

namespace App\Domains\Insurance\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property string $provider_type
 * @property string $api_adapter
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PatientPolicy> $policies
 * @property-read int|null $policies_count
 * @property-read Collection<int, InsuranceScheme> $schemes
 * @property-read int|null $schemes_count
 * @property-read Collection<int, InsuranceTariff> $tariffs
 * @property-read int|null $tariffs_count
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereApiAdapter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereContactEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereProviderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceProvider whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InsuranceProvider extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INSURANCE';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<InsuranceScheme, $this>
     */
    public function schemes(): HasMany
    {
        return $this->hasMany(InsuranceScheme::class);
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
