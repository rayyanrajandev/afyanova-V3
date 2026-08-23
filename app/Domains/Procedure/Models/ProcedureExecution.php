<?php

namespace App\Domains\Procedure\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $procedure_order_id
 * @property string $performed_by_id
 * @property string|null $assistant_id
 * @property string $execution_setting
 * @property string $anesthesia_type
 * @property string|null $wound_condition
 * @property string $findings_and_technique
 * @property string|null $post_procedure_instructions
 * @property Carbon|null $follow_up_date
 * @property Carbon $started_at
 * @property Carbon $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $assistant
 * @property-read Collection<int, ProcedureConsumableUsed> $consumables
 * @property-read int|null $consumables_count
 * @property-read ProcedureOrder $order
 * @property-read User $performedBy
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereAnesthesiaType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereAssistantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereExecutionSetting($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereFindingsAndTechnique($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereFollowUpDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution wherePerformedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution wherePostProcedureInstructions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereProcedureOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureExecution whereWoundCondition($value)
 *
 * @mixin \Eloquent
 */
class ProcedureExecution extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PROCEDURE';

    protected $guarded = ['id'];

    protected $casts = [
        'follow_up_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ProcedureOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProcedureOrder::class, 'procedure_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_id');
    }

    /**
     * @return HasMany<ProcedureConsumableUsed, $this>
     */
    public function consumables(): HasMany
    {
        return $this->hasMany(ProcedureConsumableUsed::class);
    }
}
