<?php

namespace App\Domains\Laboratory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string $lab_order_id
 * @property string $patient_id
 * @property string|null $collected_by
 * @property string $accession_number
 * @property string $sample_type
 * @property string|null $container_type
 * @property string|null $collection_site
 * @property string $status
 * @property string|null $rejection_reason
 * @property Carbon|null $collected_at
 * @property Carbon|null $received_in_lab_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $collector
 * @property-read Facility $facility
 * @property-read LabOrder $order
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereAccessionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereCollectedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereCollectedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereCollectionSite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereContainerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereLabOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereReceivedInLabAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereSampleType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabSpecimen whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LabSpecimen extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'LABORATORY';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'collected_at' => 'datetime',
        'received_in_lab_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<LabOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
