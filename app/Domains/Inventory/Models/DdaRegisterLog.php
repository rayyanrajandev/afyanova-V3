<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string $item_id
 * @property string|null $batch_id
 * @property string|null $encounter_id
 * @property string|null $patient_id
 * @property string|null $prescriber_id
 * @property string|null $administering_nurse_id
 * @property string|null $witness_user_id
 * @property numeric $dose_administered
 * @property numeric $dose_wasted_discarded
 * @property numeric $balance_before
 * @property numeric $balance_after
 * @property string|null $indication
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $administeringNurse
 * @property-read InventoryBatch|null $batch
 * @property-read Encounter|null $encounter
 * @property-read Facility $facility
 * @property-read ItemMaster $item
 * @property-read Patient|null $patient
 * @property-read User|null $prescriber
 * @property-read Tenant|null $tenant
 * @property-read User|null $witness
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereAdministeringNurseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereBalanceBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereDoseAdministered($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereDoseWastedDiscarded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereIndication($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog wherePrescriberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DdaRegisterLog whereWitnessUserId($value)
 *
 * @mixin \Eloquent
 */
class DdaRegisterLog extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'dose_administered' => 'decimal:2',
        'dose_wasted_discarded' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<ItemMaster, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'item_id');
    }

    /**
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

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
    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescriber_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function administeringNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administering_nurse_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function witness(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_user_id');
    }
}
