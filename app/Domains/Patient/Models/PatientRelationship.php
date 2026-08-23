<?php

namespace App\Domains\Patient\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $patient_id
 * @property string $related_patient_id
 * @property string $relationship_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Patient $patient
 * @property-read Patient $relatedPatient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientRelationship newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientRelationship newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientRelationship query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientRelationship whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientRelationship whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientRelationship wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientRelationship whereRelatedPatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientRelationship whereRelationshipType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientRelationship whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientRelationship whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PatientRelationship extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PATIENT';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function relatedPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'related_patient_id');
    }
}
