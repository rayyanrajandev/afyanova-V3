<?php

namespace App\Domains\Radiology\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\AuditsBulkWrites;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFacilityScope;
use App\Core\Traits\HasUuidv7;
use App\Core\Traits\ImmutableWhenFinalized;
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
 * @property string|null $radiology_study_id
 * @property string $patient_id
 * @property string $radiologist_id
 * @property string $findings
 * @property string $impression
 * @property string|null $recommendations
 * @property bool $is_critical_finding
 * @property Carbon|null $critical_notified_at
 * @property bool $is_signed
 * @property Carbon|null $signed_at
 * @property bool $is_amendment
 * @property string|null $amended_report_id
 * @property string|null $amendment_reason
 * @property bool $is_deprecated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Facility $facility
 * @property-read RadiologyOrder $order
 * @property-read RadiologyReport|null $originalReport
 * @property-read Patient $patient
 * @property-read User $radiologist
 * @property-read RadiologyStudy|null $study
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereAmendedReportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereAmendmentReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereCriticalNotifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereFindings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereImpression($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereIsAmendment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereIsCriticalFinding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereIsDeprecated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereIsSigned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereRadiologistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereRadiologyOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereRadiologyStudyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereRecommendations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereSignedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyReport whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class RadiologyReport extends Model
{
    use Auditable, AuditsBulkWrites, BelongsToTenant, HasFacilityScope, HasUuidv7, ImmutableWhenFinalized;

    const AUDIT_CATEGORY = 'RADIOLOGY';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'is_critical_finding' => 'boolean',
        'critical_notified_at' => 'datetime',
        'is_signed' => 'boolean',
        'signed_at' => 'datetime',
        'is_amendment' => 'boolean',
        'is_deprecated' => 'boolean',
    ];

    /**
     * @return BelongsTo<RadiologyOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RadiologyOrder::class, 'radiology_order_id');
    }

    /**
     * @return BelongsTo<RadiologyStudy, $this>
     */
    public function study(): BelongsTo
    {
        return $this->belongsTo(RadiologyStudy::class, 'radiology_study_id');
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
    public function radiologist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'radiologist_id');
    }

    /**
     * @return BelongsTo<RadiologyReport, $this>
     */
    public function originalReport(): BelongsTo
    {
        return $this->belongsTo(RadiologyReport::class, 'amended_report_id');
    }

    protected function isFinalized(): bool
    {
        return (bool) $this->getOriginal('is_signed');
    }
}
