<?php

namespace App\Domains\Tenancy\Models;

use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $superadmin_user_id
 * @property string $impersonated_user_id
 * @property string $impersonated_tenant_id
 * @property string $justification_reason
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ImpersonationLog extends Model
{
    use HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function superadmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superadmin_user_id');
    }

    public function impersonatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'impersonated_tenant_id');
    }
}
