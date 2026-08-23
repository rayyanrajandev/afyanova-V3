<?php

namespace App\Domains\Inpatient\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $facility_id
 * @property string $name
 * @property string $code
 * @property string $ward_type
 * @property string $gender_restriction
 * @property string|null $floor_location
 * @property numeric $daily_base_rate
 * @property bool $is_active
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Admission> $activeAdmissions
 * @property-read int|null $active_admissions_count
 * @property-read Collection<int, Bed> $beds
 * @property-read int|null $beds_count
 * @property-read Facility|null $facility
 * @property-read int $available_beds_count
 * @property-read float $occupancy_rate
 * @property-read int $occupied_beds_count
 * @property-read int $total_beds_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereDailyBaseRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereFloorLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereGenderRestriction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward whereWardType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ward withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Ward extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7, SoftDeletes;

    const AUDIT_CATEGORY = 'INPATIENT_WARD';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'daily_base_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = ['total_beds_count', 'available_beds_count', 'occupied_beds_count', 'occupancy_rate'];

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasMany<Bed, $this>
     */
    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class, 'ward_id');
    }

    /**
     * @return HasMany<Admission, $this>
     */
    public function activeAdmissions(): HasMany
    {
        return $this->hasMany(Admission::class, 'ward_id')->where('status', 'Admitted');
    }

    public function getTotalBedsCountAttribute(): int
    {
        if ($this->relationLoaded('beds')) {
            return $this->beds->count();
        }

        return $this->beds()->count();
    }

    public function getAvailableBedsCountAttribute(): int
    {
        if ($this->relationLoaded('beds')) {
            return $this->beds->where('status', 'Available')->count();
        }

        return $this->beds()->where('status', 'Available')->count();
    }

    public function getOccupiedBedsCountAttribute(): int
    {
        if ($this->relationLoaded('beds')) {
            return $this->beds->where('status', 'Occupied')->count();
        }

        return $this->beds()->where('status', 'Occupied')->count();
    }

    public function getOccupancyRateAttribute(): float
    {
        $total = $this->total_beds_count;
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->occupied_beds_count / $total) * 100, 1);
    }
}
