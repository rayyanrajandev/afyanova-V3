<?php

namespace App\Domains\Tenancy\Models;

use App\Core\Traits\HasUuidv7;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property string $status
 * @property string $plan
 * @property array<array-key, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Facility> $facilities
 * @property-read int|null $facilities_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant wherePlan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tenant whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Tenant extends Model
{
    use HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
        'settings' => 'array',
        'feature_flags' => 'array',
        'max_facilities' => 'integer',
        'max_users' => 'integer',
        'storage_quota_mb' => 'integer',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * @return HasMany<Facility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * @return HasMany<\App\Domains\Identity\Models\User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(\App\Domains\Identity\Models\User::class);
    }

    /**
     * @return HasMany<ImpersonationLog, $this>
     */
    public function impersonationLogs(): HasMany
    {
        return $this->hasMany(ImpersonationLog::class, 'impersonated_tenant_id');
    }

    public function isSuspended(): bool
    {
        return strtolower($this->subscription_status ?? $this->status) === 'suspended';
    }

    public function isActive(): bool
    {
        return strtolower($this->subscription_status ?? $this->status) === 'active' || strtolower($this->subscription_status ?? '') === 'trial';
    }

    public function hasFeature(string $feature): bool
    {
        if ($this->subscription_tier === 'enterprise') {
            return true;
        }

        $flags = $this->feature_flags ?? ['inpatient', 'pharmacy', 'laboratory', 'billing'];

        return in_array($feature, $flags);
    }

    public function canAddFacility(): bool
    {
        return $this->facilities()->count() < ($this->max_facilities ?? 5);
    }

    public function canAddUser(): bool
    {
        return $this->users()->count() < ($this->max_users ?? 50);
    }
}
