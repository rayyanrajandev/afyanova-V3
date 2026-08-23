<?php

namespace App\Domains\Scheduling\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string $ticket_number
 * @property string $priority
 * @property string $current_service_point
 * @property string $status
 * @property Carbon $joined_queue_at
 * @property Carbon|null $called_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Encounter|null $encounter
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereCalledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereCurrentServicePoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereJoinedQueueAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereTicketNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTicket whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class QueueTicket extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'SCHEDULING';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'joined_queue_at' => 'datetime',
        'called_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }
}
