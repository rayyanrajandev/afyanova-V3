<?php

namespace App\Domains\Audit\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $facility_id
 * @property string|null $user_id
 * @property string $event_category
 * @property string $action
 * @property string $entity_type
 * @property string $entity_id
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string|null $route_name
 * @property array<array-key, mixed>|null $before_state
 * @property array<array-key, mixed>|null $after_state
 * @property string|null $justification_reason
 * @property string $hash_signature
 * @property string|null $previous_hash
 * @property Carbon $created_at
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereAfterState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereBeforeState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereEventCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereHashSignature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereJustificationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog wherePreviousHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereRouteName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereUserId($value)
 *
 * @mixin \Eloquent
 */
class AuditLog extends Model
{
    use BelongsToTenant, HasUuidv7;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'before_state' => 'array',
        'after_state' => 'array',
        'created_at' => 'datetime',
    ];
}
