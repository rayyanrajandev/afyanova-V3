<?php

namespace App\Domains\Procedure\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $surgical_booking_id
 * @property string $recorded_by_id
 * @property Carbon $recorded_at
 * @property int $consciousness_score
 * @property int $activity_score
 * @property int $respiration_score
 * @property int $circulation_score
 * @property int $oxygen_saturation_score
 * @property int $total_aldrete_score
 * @property bool $discharge_ready
 * @property string|null $destination_ward_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SurgicalBooking $booking
 * @property-read Ward|null $destinationWard
 * @property-read User $recordedBy
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereActivityScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereCirculationScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereConsciousnessScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereDestinationWardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereDischargeReady($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereOxygenSaturationScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereRecordedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereRecordedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereRespirationScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereSurgicalBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereTotalAldreteScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PacuRecoveryRecord whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PacuRecoveryRecord extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PROCEDURE';

    protected $guarded = ['id'];

    protected $casts = [
        'recorded_at' => 'datetime',
        'consciousness_score' => 'integer',
        'activity_score' => 'integer',
        'respiration_score' => 'integer',
        'circulation_score' => 'integer',
        'oxygen_saturation_score' => 'integer',
        'total_aldrete_score' => 'integer',
        'discharge_ready' => 'boolean',
    ];

    /**
     * @return BelongsTo<SurgicalBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(SurgicalBooking::class, 'surgical_booking_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function destinationWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'destination_ward_id');
    }
}
