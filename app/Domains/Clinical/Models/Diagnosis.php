<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\AuditsBulkWrites;
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
 * @property string $encounter_id
 * @property string $patient_id
 * @property string $diagnosed_by
 * @property string|null $icd_10_code
 * @property string $description
 * @property string $certainty
 * @property string $type
 * @property string|null $notes
 * @property bool $is_amendment
 * @property string|null $amended_diagnosis_id
 * @property string|null $amendment_reason
 * @property bool $is_deprecated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Encounter $encounter
 * @property-read Diagnosis|null $originalDiagnosis
 * @property-read Patient $patient
 * @property-read User $provider
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereAmendedDiagnosisId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereAmendmentReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereCertainty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereDiagnosedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereIcd10Code($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereIsAmendment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereIsDeprecated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Diagnosis whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Diagnosis extends Model
{
    use Auditable, AuditsBulkWrites, BelongsToTenant, HasUuidv7, ImmutableWhenFinalized;

    const AUDIT_CATEGORY = 'CLINICAL';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'is_amendment' => 'boolean',
        'is_deprecated' => 'boolean',
    ];

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

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
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diagnosed_by');
    }

    /**
     * @return BelongsTo<Diagnosis, $this>
     */
    public function originalDiagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class, 'amended_diagnosis_id');
    }

    protected function isFinalized(): bool
    {
        return true;
    }
}
