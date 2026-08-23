<?php

namespace App\Domains\Pharmacy\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use App\Domains\Inpatient\Models\Admission;
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
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string|null $admission_id
 * @property string $reconciled_by
 * @property string $stage
 * @property string $medication_name
 * @property string|null $dosage
 * @property string|null $frequency
 * @property string|null $route
 * @property string $action_taken
 * @property string|null $clinical_rationale
 * @property string|null $substitute_medication_name
 * @property string|null $new_dosage_instructions
 * @property Carbon $reconciled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Admission|null $admission
 * @property-read Encounter|null $encounter
 * @property-read Facility $facility
 * @property-read Patient $patient
 * @property-read User $reconciler
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereActionTaken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereAdmissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereClinicalRationale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereDosage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereMedicationName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereNewDosageInstructions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereReconciledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereReconciledBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereRoute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereStage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereSubstituteMedicationName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationReconciliation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MedicationReconciliation extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PHARMACY';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'reconciled_at' => 'datetime',
    ];

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
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<Admission, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
