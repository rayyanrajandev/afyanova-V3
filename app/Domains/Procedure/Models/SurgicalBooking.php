<?php

namespace App\Domains\Procedure\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $booking_number
 * @property string $procedure_order_id
 * @property string $operating_suite_id
 * @property string $lead_surgeon_id
 * @property string|null $anesthetist_id
 * @property string|null $scrub_nurse_id
 * @property Carbon $scheduled_start
 * @property Carbon $scheduled_end
 * @property string $urgency
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $anesthetist
 * @property-read User $leadSurgeon
 * @property-read ProcedureOrder $order
 * @property-read PacuRecoveryRecord|null $pacuRecord
 * @property-read User|null $scrubNurse
 * @property-read OperatingSuite $suite
 * @property-read Tenant|null $tenant
 * @property-read WhoSurgicalChecklist|null $whoChecklist
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereAnesthetistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereBookingNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereLeadSurgeonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereOperatingSuiteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereProcedureOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereScheduledEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereScheduledStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereScrubNurseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurgicalBooking whereUrgency($value)
 *
 * @mixin \Eloquent
 */
class SurgicalBooking extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PROCEDURE';

    protected $guarded = ['id'];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
    ];

    /**
     * @return BelongsTo<ProcedureOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProcedureOrder::class, 'procedure_order_id');
    }

    /**
     * @return BelongsTo<OperatingSuite, $this>
     */
    public function suite(): BelongsTo
    {
        return $this->belongsTo(OperatingSuite::class, 'operating_suite_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function leadSurgeon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_surgeon_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anesthetist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anesthetist_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scrubNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scrub_nurse_id');
    }

    /**
     * @return HasOne<WhoSurgicalChecklist, $this>
     */
    public function whoChecklist(): HasOne
    {
        return $this->hasOne(WhoSurgicalChecklist::class);
    }

    /**
     * @return HasOne<PacuRecoveryRecord, $this>
     */
    public function pacuRecord(): HasOne
    {
        return $this->hasOne(PacuRecoveryRecord::class);
    }
}
