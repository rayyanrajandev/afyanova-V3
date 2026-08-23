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
 * @property string $contact_type
 * @property string $value
 * @property bool $is_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact whereContactType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact whereIsVerified($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientContact whereValue($value)
 *
 * @mixin \Eloquent
 */
class PatientContact extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PATIENT';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'is_verified' => 'boolean',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
