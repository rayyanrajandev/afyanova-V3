<?php

namespace App\Domains\Inpatient\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFacilityScope;
use App\Core\Traits\HasUuidv7;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $facility_id
 * @property string $admission_id
 * @property string|null $encounter_id
 * @property string $patient_id
 * @property string|null $item_master_id
 * @property string|null $medication_id
 * @property string|null $location_id
 * @property string $item_name
 * @property string|null $batch_number
 * @property Carbon|null $expiry_date
 * @property numeric $dose_quantity
 * @property string $dose_unit
 * @property string $route
 * @property string|null $frequency
 * @property Carbon|null $scheduled_time
 * @property Carbon $administered_at
 * @property string $administered_by
 * @property string|null $witness_by
 * @property bool $witness_pin_verified
 * @property string $status
 * @property bool $is_dda_narcotic
 * @property bool $is_billed
 * @property numeric $charge_amount
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $administeredByUser
 * @property-read Admission|null $admission
 * @property-read Encounter|null $encounter
 * @property-read Facility|null $facility
 * @property-read ItemMaster|null $itemMaster
 * @property-read InventoryLocation|null $location
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 * @property-read User|null $witnessUser
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereAdministeredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereAdministeredBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereAdmissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereBatchNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereChargeAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereDoseQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereDoseUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereIsBilled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereIsDdaNarcotic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereItemMasterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereItemName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereMedicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereRoute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereScheduledTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereWitnessBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord whereWitnessPinVerified($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministrationRecord withoutTrashed()
 *
 * @property-read MedicationFormulary|null $medication
 *
 * @mixin \Eloquent
 */
class MedicationAdministrationRecord extends Model
{
    use Auditable, BelongsToTenant, HasFacilityScope, HasUuidv7, SoftDeletes;

    const AUDIT_CATEGORY = 'INPATIENT_MAR';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'dose_quantity' => 'decimal:2',
        'charge_amount' => 'decimal:2',
        'expiry_date' => 'date',
        'scheduled_time' => 'datetime',
        'administered_at' => 'datetime',
        'witness_pin_verified' => 'boolean',
        'is_dda_narcotic' => 'boolean',
        'is_billed' => 'boolean',
    ];

    /**
     * @return BelongsTo<Admission, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

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
     * @return BelongsTo<ItemMaster, $this>
     */
    public function itemMaster(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_master_id');
    }

    /**
     * @return BelongsTo<MedicationFormulary, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationFormulary::class, 'medication_id');
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function administeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function witnessUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_by');
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
