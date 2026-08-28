<?php

namespace App\Core\Traits;

use App\Core\Context\BreakGlassContext;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

trait HasFacilityScope
{
    /**
     * Restricts visibility of a facility-tagged, patient-tied record
     * (appointment, queue ticket, encounter, invoice) to facilities the
     * acting user actually has a reason to see — same principle as
     * Patient::booted()'s facility scope, but simpler: these models carry
     * facility_id and patient_id directly, so there's no need for
     * Patient's registered_at_facility_id-or-via-encounters fallback.
     * Kept as a separate trait rather than unifying with Patient's scope,
     * since forcing both shapes into one abstraction would touch Patient's
     * already-correct, already-tested scope for no behavioral gain.
     *
     * Convenience/need-to-know boundary layered on top of tenant isolation
     * (Postgres RLS on tenant_id is unaffected either way), not a
     * replacement for it. Deliberately permissive at the edges: a user
     * with no facility-scoped role assignment at all, or a row with no
     * facility_id, is not restricted — this scope only narrows once
     * there's an actual facility assignment and an actual facility_id to
     * compare.
     */
    public static function bootHasFacilityScope(): void
    {
        static::addGlobalScope('facility', function (Builder $builder) {
            $user = Auth::user();

            if (! $user instanceof User) {
                return;
            }

            if ($user->roleAssignments()->whereNull('facility_id')->exists()) {
                return;
            }

            $assignedFacilityIds = $user->roleAssignments()->whereNotNull('facility_id')->pluck('facility_id');

            if ($assignedFacilityIds->isEmpty()) {
                return;
            }

            $breakGlassPatientId = App::make(BreakGlassContext::class)->getPatientId();

            $builder->where(function (Builder $query) use ($assignedFacilityIds, $breakGlassPatientId) {
                $query->whereNull('facility_id')
                    ->orWhereIn('facility_id', $assignedFacilityIds);

                if ($breakGlassPatientId) {
                    $query->orWhere('patient_id', $breakGlassPatientId);
                }
            });
        });
    }
}
