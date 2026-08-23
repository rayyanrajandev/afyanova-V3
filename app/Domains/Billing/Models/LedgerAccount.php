<?php

namespace App\Domains\Billing\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LedgerAccount extends Model
{
    use BelongsToTenant, HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
    ];
}
