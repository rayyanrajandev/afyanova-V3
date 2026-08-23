<?php

namespace App\Domains\Inpatient\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $facility_id
 * @property string $ward_id
 * @property string $bed_number
 * @property string $bed_type
 * @property numeric $daily_rate_amount
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Admission|null $currentAdmission
 * @property-read Facility|null $facility
 * @property-read Tenant $tenant
 * @property-read Ward|null $ward
 *
 * @method static Builder<static>|Bed available()
 * @method static Builder<static>|Bed newModelQuery()
 * @method static Builder<static>|Bed newQuery()
 * @method static Builder<static>|Bed occupied()
 * @method static Builder<static>|Bed onlyTrashed()
 * @method static Builder<static>|Bed query()
 * @method static Builder<static>|Bed whereBedNumber($value)
 * @method static Builder<static>|Bed whereBedType($value)
 * @method static Builder<static>|Bed whereCreatedAt($value)
 * @method static Builder<static>|Bed whereDailyRateAmount($value)
 * @method static Builder<static>|Bed whereDeletedAt($value)
 * @method static Builder<static>|Bed whereFacilityId($value)
 * @method static Builder<static>|Bed whereId($value)
 * @method static Builder<static>|Bed whereNotes($value)
 * @method static Builder<static>|Bed whereStatus($value)
 * @method static Builder<static>|Bed whereTenantId($value)
 * @method static Builder<static>|Bed whereUpdatedAt($value)
 * @method static Builder<static>|Bed whereWardId($value)
 * @method static Builder<static>|Bed withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Bed withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Bed extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7, SoftDeletes;

    const AUDIT_CATEGORY = 'INPATIENT_BED';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'daily_rate_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasOne<Admission, $this>
     */
    public function currentAdmission(): HasOne
    {
        return $this->hasOne(Admission::class, 'bed_id')->where('status', 'Admitted');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'Available');
    }

    public function scopeOccupied(Builder $query): Builder
    {
        return $query->where('status', 'Occupied');
    }
}
