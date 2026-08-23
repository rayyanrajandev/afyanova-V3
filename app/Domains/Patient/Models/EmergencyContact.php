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
 * @property string $name
 * @property string $relationship
 * @property string $phone_number
 * @property string|null $alternative_phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact whereAlternativePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact whereRelationship($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmergencyContact whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class EmergencyContact extends Model
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
        return $this->belongsTo(Patient::class);
    }
}
