<?php

namespace App\Domains\Identity\Models;

use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Department;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $role_id
 * @property string|null $facility_id
 * @property string|null $department_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department|null $department
 * @property-read Facility|null $facility
 * @property-read Role $role
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleAssignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleAssignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleAssignment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleAssignment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleAssignment whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleAssignment whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleAssignment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleAssignment whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleAssignment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleAssignment whereUserId($value)
 *
 * @mixin \Eloquent
 */
class RoleAssignment extends Model
{
    use HasUuidv7;

    const AUDIT_CATEGORY = 'AUTH';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
