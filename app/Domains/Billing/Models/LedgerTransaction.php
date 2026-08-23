<?php

namespace App\Domains\Billing\Models;

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
 * @property string $facility_id
 * @property string $user_id
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string $description
 * @property Carbon $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LedgerEntry> $entries
 * @property-read int|null $entries_count
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction wherePostedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction whereReferenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction whereReferenceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerTransaction whereUserId($value)
 *
 * @mixin \Eloquent
 */
class LedgerTransaction extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'FINANCE';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'posted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id');
    }
}
