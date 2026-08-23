<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
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
 * @property string|null $encounter_id
 * @property string $icd10_code
 * @property string $problem_name
 * @property string $status
 * @property string $clinical_status
 * @property string $severity
 * @property Carbon|null $onset_date
 * @property Carbon|null $resolved_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Encounter|null $encounter
 * @property-read Patient $patient
 * @property-read User $recordedBy
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereClinicalStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereIcd10Code($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereOnsetDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereProblemName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereRecordedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereResolvedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereSeverity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientProblem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PatientProblem extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'CLINICAL';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'onset_date' => 'date',
        'resolved_date' => 'date',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
