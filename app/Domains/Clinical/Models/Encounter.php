<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Identity\Models\User;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Procedure\Models\ProcedureOrder;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string|null $department_id
 * @property string $patient_id
 * @property string|null $provider_id
 * @property string $encounter_type
 * @property string $status
 * @property Carbon $start_time
 * @property Carbon|null $end_time
 * @property string|null $reason_for_visit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InsuranceClaim> $claims
 * @property-read int|null $claims_count
 * @property-read Collection<int, Diagnosis> $diagnoses
 * @property-read int|null $diagnoses_count
 * @property-read Facility $facility
 * @property-read Collection<int, Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read Collection<int, LabOrder> $labOrders
 * @property-read int|null $lab_orders_count
 * @property-read Collection<int, ClinicalNote> $notes
 * @property-read int|null $notes_count
 * @property-read Patient $patient
 * @property-read Collection<int, Prescription> $prescriptions
 * @property-read int|null $prescriptions_count
 * @property-read Collection<int, ProcedureOrder> $procedureOrders
 * @property-read int|null $procedure_orders_count
 * @property-read User|null $provider
 * @property-read QueueTicket|null $queueTicket
 * @property-read Tenant $tenant
 * @property-read Collection<int, ClinicalVital> $vitals
 * @property-read int|null $vitals_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereEncounterType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereReasonForVisit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Encounter whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Encounter extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'CLINICAL';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return HasMany<ClinicalVital, $this>
     */
    public function vitals(): HasMany
    {
        return $this->hasMany(ClinicalVital::class)->where('is_deprecated', false);
    }

    /**
     * @return HasMany<ClinicalNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class)->where('is_deprecated', false);
    }

    /**
     * @return HasMany<Diagnosis, $this>
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class)->where('is_deprecated', false);
    }

    /**
     * @return HasMany<Prescription, $this>
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
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
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<LabOrder, $this>
     */
    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function queueTicket()
    {
        return $this->hasOne(QueueTicket::class);
    }

    /**
     * @return HasMany<InsuranceClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    /**
     * @return HasMany<ProcedureOrder, $this>
     */
    public function procedureOrders(): HasMany
    {
        return $this->hasMany(ProcedureOrder::class);
    }

    /**
     * @return HasMany<ClinicalConsent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(ClinicalConsent::class);
    }

    /**
     * @return HasMany<ClinicalReferral, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(ClinicalReferral::class);
    }

    /**
     * @return HasMany<PatientImmunization, $this>
     */
    public function immunizations(): HasMany
    {
        return $this->hasMany(PatientImmunization::class);
    }

    /**
     * @return HasMany<AncEncounter, $this>
     */
    public function ancEncounters(): HasMany
    {
        return $this->hasMany(AncEncounter::class);
    }

    /**
     * @return HasMany<PartographEntry, $this>
     */
    public function partographEntries(): HasMany
    {
        return $this->hasMany(PartographEntry::class);
    }

    /**
     * @return HasMany<RadiologyOrder, $this>
     */
    public function radiologyOrders(): HasMany
    {
        return $this->hasMany(RadiologyOrder::class);
    }
}
