<?php

namespace App\Domains\Inpatient\Models;

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
 * @property string|null $facility_id
 * @property string $admission_id
 * @property string $from_ward_id
 * @property string $from_bed_id
 * @property string $to_ward_id
 * @property string $to_bed_id
 * @property Carbon $transferred_at
 * @property string $transferred_by
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Admission|null $admission
 * @property-read Bed|null $fromBed
 * @property-read Ward|null $fromWard
 * @property-read User $performer
 * @property-read Tenant $tenant
 * @property-read Bed|null $toBed
 * @property-read Ward|null $toWard
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereAdmissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereFromBedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereFromWardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereToBedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereToWardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereTransferredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereTransferredBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BedTransfer whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BedTransfer extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INPATIENT_TRANSFER';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'transferred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Admission, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function fromWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'from_ward_id');
    }

    /**
     * @return BelongsTo<Bed, $this>
     */
    public function fromBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'from_bed_id');
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function toWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'to_ward_id');
    }

    /**
     * @return BelongsTo<Bed, $this>
     */
    public function toBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'to_bed_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
