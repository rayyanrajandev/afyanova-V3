<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Procedure\Models\ProcedureOrder;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string|null $procedure_order_id
 * @property string $clinician_id
 * @property string $consent_type
 * @property string $procedure_title
 * @property string $explanation_of_risks
 * @property string|null $alternative_treatments
 * @property string $signatory_type
 * @property string $signatory_name
 * @property string|null $signature_fingerprint_token
 * @property string|null $witness_name
 * @property bool $interpreter_used
 * @property string $language_used
 * @property Carbon $signed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $clinician
 * @property-read Encounter|null $encounter
 * @property-read Facility $facility
 * @property-read Patient $patient
 * @property-read ProcedureOrder|null $procedureOrder
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereAlternativeTreatments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereClinicianId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereConsentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereExplanationOfRisks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereInterpreterUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereLanguageUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereProcedureOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereProcedureTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereSignatoryName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereSignatoryType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereSignatureFingerprintToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereSignedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalConsent whereWitnessName($value)
 *
 * @mixin \Eloquent
 */
class ClinicalConsent extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'CLINICAL';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'interpreter_used' => 'boolean',
        'signed_at' => 'datetime',
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
     * @return BelongsTo<ProcedureOrder, $this>
     */
    public function procedureOrder(): BelongsTo
    {
        return $this->belongsTo(ProcedureOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
