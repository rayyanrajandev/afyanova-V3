<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Core\Traits\ImmutableWhenFinalized;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $patient_id
 * @property string $recorded_by
 * @property string $allergen_type
 * @property string $allergen
 * @property string|null $reaction
 * @property string $severity
 * @property string $status
 * @property bool $is_amendment
 * @property string|null $amended_allergy_id
 * @property string|null $amendment_reason
 * @property bool $is_deprecated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Allergy|null $originalAllergy
 * @property-read Patient $patient
 * @property-read User $recorder
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereAllergen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereAllergenType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereAmendedAllergyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereAmendmentReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereIsAmendment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereIsDeprecated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereReaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereRecordedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereSeverity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allergy whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Allergy extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7, ImmutableWhenFinalized;

    const AUDIT_CATEGORY = 'CLINICAL';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'is_amendment' => 'boolean',
        'is_deprecated' => 'boolean',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<Allergy, $this>
     */
    public function originalAllergy(): BelongsTo
    {
        return $this->belongsTo(Allergy::class, 'amended_allergy_id');
    }

    protected function isFinalized(): bool
    {
        return true;
    }
}
