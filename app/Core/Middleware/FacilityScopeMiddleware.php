<?php

namespace App\Core\Middleware;

use App\Core\Context\FacilityContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FacilityScopeMiddleware
{
    /**
     * Resolve the facility the request claims to act within, and verify the
     * authenticated user actually has a role assignment there (or a global
     * assignment) before trusting it. Unlike the raw X-Facility-ID header
     * this middleware replaces as the source of truth, a claimed facility
     * that fails verification is rejected rather than silently accepted.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var FacilityContext $context */
        $context = App::make(FacilityContext::class);

        $claimedFacilityId = $request->route('facility')
            ?? $request->header('X-Facility-ID')
            ?? $request->input('facility_id');

        $user = $request->user();

        if ($claimedFacilityId && $user) {
            $hasAccess = $user->roleAssignments()
                ->where(function ($query) use ($claimedFacilityId) {
                    $query->where('facility_id', $claimedFacilityId)
                        ->orWhereNull('facility_id');
                })
                ->exists();

            if (! $hasAccess) {
                Log::warning('FacilityScopeMiddleware rejected an unassigned facility claim.', [
                    'user_id' => $user->id,
                    'claimed_facility_id' => $claimedFacilityId,
                ]);

                abort(403, 'You are not assigned to this facility.');
            }

            $context->setFacilityId($claimedFacilityId);
        }

        return $next($request);
    }
}
