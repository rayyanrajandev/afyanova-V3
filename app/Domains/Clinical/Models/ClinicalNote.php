<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\AuditsBulkWrites;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Core\Traits\ImmutableWhenFinalized;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $encounter_id
 * @property string $patient_id
 * @property string $author_id
 * @property string $note_type
 * @property array<array-key, mixed> $content
 * @property bool $is_signed
 * @property Carbon|null $signed_at
 * @property bool $is_amendment
 * @property string|null $amended_note_id
 * @property string|null $amendment_reason
 * @property bool $is_deprecated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $author
 * @property-read Encounter $encounter
 * @property-read ClinicalNote|null $originalNote
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereAmendedNoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereAmendmentReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereIsAmendment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereIsDeprecated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereIsSigned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereNoteType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereSignedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalNote whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ClinicalNote extends Model
{
    use Auditable, AuditsBulkWrites, BelongsToTenant, HasUuidv7, ImmutableWhenFinalized;

    const AUDIT_CATEGORY = 'CLINICAL';

    const AUDIT_REDACT = ['content', 'amendment_reason'];

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'content' => 'array',
        'is_signed' => 'boolean',
        'signed_at' => 'datetime',
        'is_amendment' => 'boolean',
        'is_deprecated' => 'boolean',
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<ClinicalNote, $this>
     */
    public function originalNote(): BelongsTo
    {
        return $this->belongsTo(ClinicalNote::class, 'amended_note_id');
    }

    protected function isFinalized(): bool
    {
        // The pre-update value: signing itself is the false-to-true write
        // that must be allowed through; only a note that was ALREADY
        // signed before this write is locked.
        return (bool) $this->getOriginal('is_signed');
    }
}
