<?php

namespace App\Domains\Procedure\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
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
 * @property string $name
 * @property string $suite_code
 * @property string $suite_type
 * @property string $status
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SurgicalBooking> $bookings
 * @property-read int|null $bookings_count
 * @property-read Facility $facility
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite whereSuiteCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite whereSuiteType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperatingSuite whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class OperatingSuite extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PROCEDURE';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasMany<SurgicalBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(SurgicalBooking::class);
    }
}
