<?php

namespace App\Domains\Scheduling\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string|null $department_id
 * @property string $provider_id
 * @property int $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property int $slot_duration_minutes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $provider
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereDayOfWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereSlotDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSchedule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ProviderSchedule extends Model
{
    use BelongsToTenant, HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
        'day_of_week' => 'integer',
        'slot_duration_minutes' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
}
