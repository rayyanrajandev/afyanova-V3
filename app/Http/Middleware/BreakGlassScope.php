<?php

namespace App\Http\Middleware;

use App\Core\Context\BreakGlassContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Break-Glass Scope Middleware.
 *
 * Reads the `break_glass.patient_id` / `break_glass.expires_at` session
 * keys that BreakGlassController writes. When a valid, non-expired
 * break-glass key is present, this middleware populates BreakGlassContext
 * for the duration of the current request only — Patient::booted()'s
 * facility-visibility scope is what actually reads it to admit that one
 * patient regardless of the acting user's own facility assignments:
 *
 *  - It does NOT modify the database session setting permanently.
 *  - It does NOT persist the widened scope to the session.
 *  - It ONLY allows access to the specific patient stored in the session.
 *
 * If the break-glass key has expired it is silently cleared so that
 * subsequent requests fall back to normal facility scoping.
 *
 * ORDERING: Must run after TenantContextMiddleware (so tenant is always
 * enforced) and before SubstituteBindings (so implicit route-model
 * binding on a {patient} route param sees the widened scope too) —
 * tenant isolation is never bypassed either way, since the Postgres RLS
 * policy on app.current_tenant_id is untouched by any of this.
 */
class BreakGlassScope
{
    public function __construct(
        private readonly BreakGlassContext $breakGlassContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $patientId = $request->session()->get('break_glass.patient_id');
        $expiresAt = $request->session()->get('break_glass.expires_at');

        // Clear expired break-glass session keys.
        if ($patientId && $expiresAt && time() > $expiresAt) {
            $request->session()->forget(['break_glass.patient_id', 'break_glass.expires_at']);
            $patientId = null;
        }

        if ($patientId && $expiresAt) {
            $this->breakGlassContext->setPatientId($patientId);
            $request->attributes->set('break_glass_active', true);
            $request->attributes->set('break_glass_patient_id', $patientId);
        }

        return $next($request);
    }
}
