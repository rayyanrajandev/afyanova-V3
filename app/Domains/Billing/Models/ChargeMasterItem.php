<?php

namespace App\Domains\Billing\Models;

use App\Core\Traits\Auditable;
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
 * @property string $category
 * @property numeric $unit_price
 * @property string $currency
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereEffectiveFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereEffectiveTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeMasterItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ChargeMasterItem extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'BILLING';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'unit_price' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];
}
