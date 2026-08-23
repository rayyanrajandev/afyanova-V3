<?php

namespace App\Domains\Patient\Models;

use App\Core\Context\BreakGlassContext;
use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Models\Allergy;
use App\Domains\Clinical\Models\ClinicalReferral;
use App\Domains\Clinical\Models\ClinicalVital;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\PatientProblem;
use App\Domains\Identity\Models\User;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Insurance\Models\PatientPolicy;
use App\Domains\Pharmacy\Models\MedicationReconciliation;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Tenancy\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $primary_mrn
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property \Illuminate\Support\Carbon|null $dob
 * @property string $gender
 * @property string|null $blood_group
 * @property string|null $marital_status
 * @property string|null $occupation
 * @property string|null $nationality
 * @property string $status
 * @property string|null $merged_into_patient_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collection<int, Allergy> $allergies
 * @property-read int|null $allergies_count
 * @property-read Collection<int, Appointment> $appointments
 * @property-read int|null $appointments_count
 * @property-read Collection<int, InsuranceClaim> $claims
 * @property-read int|null $claims_count
 * @property-read Collection<int, PatientContact> $contacts
 * @property-read int|null $contacts_count
 * @property-read Collection<int, EmergencyContact> $emergencyContacts
 * @property-read int|null $emergency_contacts_count
 * @property-read Collection<int, Encounter> $encounters
 * @property-read int|null $encounters_count
 * @property-read int|null $age
 * @property-read string|null $formatted_dob
 * @property-read Collection<int, PatientIdentifier> $identifiers
 * @property-read int|null $identifiers_count
 * @property-read Collection<int, Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read ClinicalVital|null $latestVital
 * @property-read Collection<int, PatientPolicy> $policies
 * @property-read int|null $policies_count
 * @property-read Collection<int, PatientRelationship> $relationships
 * @property-read int|null $relationships_count
 * @property-read Tenant $tenant
 * @property-read Collection<int, ClinicalVital> $vitals
 * @property-read int|null $vitals_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereBloodGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereDob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereMaritalStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereMergedIntoPatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereMiddleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereOccupation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient wherePrimaryMrn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereUpdatedAt($value)
 *
 * @property string|null $registered_at_facility_id
 *
 * @method static Builder<static>|Patient whereRegisteredAtFacilityId($value)
 *
 * @mixin \Eloquent
 */
class Patient extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PATIENT';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'dob' => 'date:Y-m-d',
    ];

    protected $appends = ['age', 'formatted_dob'];

    /**
     * Restricts patient visibility to facilities the acting user actually
     * has a reason to see: where the patient was registered, a facility
     * they've since been seen at (encounter), a global (tenant-wide) role
     * assignment, or an active break-glass override for that one patient.
     *
     * This is a convenience/need-to-know boundary on top of tenant
     * isolation, not a replacement for it — Postgres RLS on tenant_id is
     * unaffected either way. Deliberately permissive at the edges: a user
     * with no facility-scoped role assignment at all, or a patient with
     * no registered_at_facility_id (data predating this column, or
     * registered through a path with no facility context), is not
     * restricted — this scope only narrows once there's an actual
     * facility assignment and an actual registering facility to compare.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('facility', function (Builder $builder) {
            $user = Auth::user();

            if (! $user instanceof User) {
                return;
            }

            if ($user->roleAssignments()->whereNull('facility_id')->exists()) {
                return;
            }

            $assignedFacilityIds = $user->roleAssignments()->whereNotNull('facility_id')->pluck('facility_id');

            if ($assignedFacilityIds->isEmpty()) {
                return;
            }

            $breakGlassPatientId = App::make(BreakGlassContext::class)->getPatientId();

            $builder->where(function (Builder $query) use ($assignedFacilityIds, $breakGlassPatientId) {
                $query->whereNull('registered_at_facility_id')
                    ->orWhereIn('registered_at_facility_id', $assignedFacilityIds)
                    ->orWhereHas('encounters', function (Builder $encounterQuery) use ($assignedFacilityIds) {
                        $encounterQuery->whereIn('facility_id', $assignedFacilityIds);
                    });

                if ($breakGlassPatientId) {
                    $query->orWhere('id', $breakGlassPatientId);
                }
            });
        });
    }

    public function getAgeAttribute(): ?int
    {
        if (! $this->dob) {
            return null;
        }

        return (int) Carbon::parse($this->dob)->age;
    }

    public function getFormattedDobAttribute(): ?string
    {
        if (! $this->dob) {
            return null;
        }

        return Carbon::parse($this->dob)->format('d M Y');
    }

    public function isDeceased(): bool
    {
        return strcasecmp($this->status ?? '', 'Deceased') === 0 || ! empty($this->deceased_at);
    }

    public function isMerged(): bool
    {
        return strcasecmp($this->status ?? '', 'Merged') === 0 || ! empty($this->merged_into_patient_id);
    }

    /**
     * @return HasMany<PatientIdentifier, $this>
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(PatientIdentifier::class);
    }

    /**
     * @return HasMany<PatientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(PatientContact::class);
    }

    /**
     * @return HasMany<EmergencyContact, $this>
     */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    /**
     * @return HasMany<PatientRelationship, $this>
     */
    public function relationships(): HasMany
    {
        return $this->hasMany(PatientRelationship::class);
    }

    /**
     * @return HasMany<Allergy, $this>
     */
    public function allergies(): HasMany
    {
        return $this->hasMany(Allergy::class);
    }

    /**
     * @return HasMany<Encounter, $this>
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<ClinicalVital, $this>
     */
    public function vitals(): HasMany
    {
        return $this->hasMany(ClinicalVital::class);
    }

    /**
     * @return HasOne<ClinicalVital, $this>
     */
    public function latestVital(): HasOne
    {
        return $this->hasOne(ClinicalVital::class)->latestOfMany('created_at');
    }

    /**
     * @return HasMany<PatientPolicy, $this>
     */
    public function policies(): HasMany
    {
        return $this->hasMany(PatientPolicy::class);
    }

    /**
     * @return HasMany<InsuranceClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    /**
     * @return HasMany<PatientProblem, $this>
     */
    public function problems(): HasMany
    {
        return $this->hasMany(PatientProblem::class);
    }

    /**
     * @return HasMany<MedicationReconciliation, $this>
     */
    public function medicationReconciliations(): HasMany
    {
        return $this->hasMany(MedicationReconciliation::class);
    }

    /**
     * @return HasMany<ClinicalReferral, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(ClinicalReferral::class);
    }

    /**
     * @return HasMany<RadiologyOrder, $this>
     */
    public function radiologyOrders(): HasMany
    {
        return $this->hasMany(RadiologyOrder::class);
    }
}
