<?php

namespace App\Domains\Procedure\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $order_number
 * @property string $encounter_id
 * @property string $patient_id
 * @property string|null $ordering_provider_id
 * @property string $procedure_catalog_id
 * @property string $priority
 * @property string|null $clinical_indication
 * @property string $status
 * @property Carbon $ordered_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProcedureCatalog $catalog
 * @property-read Encounter $encounter
 * @property-read Collection<int, ProcedureExecution> $executions
 * @property-read int|null $executions_count
 * @property-read ProcedureExecution|null $latestExecution
 * @property-read User|null $orderingProvider
 * @property-read Patient $patient
 * @property-read SurgicalBooking|null $surgicalBooking
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereClinicalIndication($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereOrderedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereOrderingProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereProcedureCatalogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureOrder whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ProcedureOrder extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PROCEDURE';

    protected $guarded = ['id'];

    protected $casts = [
        'ordered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

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
    public function orderingProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordering_provider_id');
    }

    /**
     * @return BelongsTo<ProcedureCatalog, $this>
     */
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ProcedureCatalog::class, 'procedure_catalog_id');
    }

    /**
     * @return HasMany<ProcedureExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(ProcedureExecution::class);
    }

    /**
     * @return HasOne<ProcedureExecution, $this>
     */
    public function latestExecution(): HasOne
    {
        return $this->hasOne(ProcedureExecution::class)->latestOfMany('completed_at');
    }

    /**
     * @return HasOne<SurgicalBooking, $this>
     */
    public function surgicalBooking(): HasOne
    {
        return $this->hasOne(SurgicalBooking::class);
    }
}
