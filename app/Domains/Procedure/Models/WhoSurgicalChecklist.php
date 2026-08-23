<?php

namespace App\Domains\Procedure\Models;

use App\Core\Traits\Auditable;
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
 * @property string $surgical_booking_id
 * @property Carbon|null $sign_in_completed_at
 * @property string|null $sign_in_verified_by
 * @property Carbon|null $time_out_completed_at
 * @property string|null $time_out_verified_by
 * @property Carbon|null $sign_out_completed_at
 * @property string|null $sign_out_verified_by
 * @property bool $sponge_and_needle_count_correct
 * @property bool $specimens_labeled_correctly
 * @property array<array-key, mixed>|null $checklist_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SurgicalBooking $booking
 * @property-read User|null $signInVerifier
 * @property-read User|null $signOutVerifier
 * @property-read Tenant|null $tenant
 * @property-read User|null $timeOutVerifier
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereChecklistData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereSignInCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereSignInVerifiedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereSignOutCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereSignOutVerifiedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereSpecimensLabeledCorrectly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereSpongeAndNeedleCountCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereSurgicalBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereTimeOutCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereTimeOutVerifiedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhoSurgicalChecklist whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class WhoSurgicalChecklist extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PROCEDURE';

    protected $guarded = ['id'];

    protected $casts = [
        'sign_in_completed_at' => 'datetime',
        'time_out_completed_at' => 'datetime',
        'sign_out_completed_at' => 'datetime',
        'sponge_and_needle_count_correct' => 'boolean',
        'specimens_labeled_correctly' => 'boolean',
        'checklist_data' => 'array',
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
    public function signInVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sign_in_verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function timeOutVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'time_out_verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signOutVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sign_out_verified_by');
    }
}
