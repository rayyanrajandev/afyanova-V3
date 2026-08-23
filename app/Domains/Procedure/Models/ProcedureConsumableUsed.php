<?php

namespace App\Domains\Procedure\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $procedure_execution_id
 * @property string $item_name
 * @property string|null $medication_id
 * @property string|null $batch_id
 * @property numeric $quantity_used
 * @property numeric $unit_price
 * @property bool $is_billed_to_patient
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryBatch|null $batch
 * @property-read ProcedureExecution $execution
 * @property-read MedicationFormulary|null $medication
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereIsBilledToPatient($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereItemName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereMedicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereProcedureExecutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereQuantityUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureConsumableUsed whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ProcedureConsumableUsed extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PROCEDURE';

    protected $table = 'procedure_consumables_used';

    protected $guarded = ['id'];

    protected $casts = [
        'quantity_used' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'is_billed_to_patient' => 'boolean',
    ];

    /**
     * @return BelongsTo<ProcedureExecution, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProcedureExecution::class, 'procedure_execution_id');
    }

    /**
     * @return BelongsTo<MedicationFormulary, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationFormulary::class, 'medication_id');
    }

    /**
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }
}
