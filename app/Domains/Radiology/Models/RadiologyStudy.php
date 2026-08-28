<?php

namespace App\Domains\Radiology\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFacilityScope;
use App\Core\Traits\HasUuidv7;
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
 * @property string $radiology_order_id
 * @property string $patient_id
 * @property string|null $technician_id
 * @property string|null $study_instance_uid
 * @property string|null $accession_number
 * @property int $series_count
 * @property int $instance_count
 * @property string|null $pacs_storage_url
 * @property string|null $technician_notes
 * @property Carbon $acquired_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Facility $facility
 * @property-read RadiologyOrder $order
 * @property-read Patient $patient
 * @property-read User|null $technician
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereAccessionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereAcquiredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereInstanceCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy wherePacsStorageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereRadiologyOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereSeriesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereStudyInstanceUid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereTechnicianId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereTechnicianNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyStudy whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class RadiologyStudy extends Model
{
    use Auditable, BelongsToTenant, HasFacilityScope, HasUuidv7;

    const AUDIT_CATEGORY = 'RADIOLOGY';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'series_count' => 'integer',
        'instance_count' => 'integer',
        'acquired_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<RadiologyOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RadiologyOrder::class, 'radiology_order_id');
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
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
