<?php

namespace App\Http\Controllers\Clinical;

use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Patient\Models\Patient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Break-Glass Emergency Access Controller.
 *
 * Break-glass allows a clinician who holds the `clinical.break_glass`
 * permission to override their normal facility scope to access a patient
 * record in a genuine emergency — e.g. a trauma patient arriving from a
 * different facility within the same tenant.
 *
 * Design decisions:
 *
 *  SCOPE: Cross-facility within the same tenant only. Cross-tenant access
 *  (a different hospital organisation entirely) would require bypassing
 *  PostgreSQL RLS and is a fundamentally different risk level.
 *
 *  TIME-LIMIT: The override is valid for 5 minutes. After that,
 *  BreakGlassScope clears the session keys automatically.
 *
 *  AUDIT: Every activation writes a BREAK_GLASS audit entry with the
 *  free-text justification. Every revocation writes BREAK_GLASS_REVOKED.
 *  These entries sit on the tenant's hash-chained audit trail and cannot
 *  be deleted (Postgres rule: no_delete_audit).
 *
 *  MANDATORY JUSTIFICATION: Minimum 20 characters. Short strings like
 *  "emergency" are rejected — the audit trail must carry enough clinical
 *  context to be defensible in a retrospective review.
 */
class BreakGlassController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AuthorizationService $authService,
    ) {}

    /**
     * Activate break-glass access for one patient.
     * Stores a time-limited session key that BreakGlassScope reads on
     * subsequent requests to widen the facility scope for that request only.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $hasPermission = $this->authService->hasPermission($user, 'clinical.break_glass')
            || $this->authService->isTenantAdmin($user)
            || $user->roleAssignments()->whereHas('role.permissions', fn ($q) => $q->where('slug', 'clinical.break_glass'))->exists();

        abort_unless($hasPermission, 403, 'You do not have break-glass emergency override authorization.');

        $validated = $request->validate([
            'patient_id' => ['required', 'uuid'],
            'justification' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        // Verify the patient exists within the user's own tenant.
        // We deliberately bypass the facility scope here — that's the point of
        // break-glass — but we NEVER bypass the tenant scope.
        $patient = Patient::withoutGlobalScopes(['facility'])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->findOrFail($validated['patient_id']);

        // Write the audit entry first — if this fails, we don't activate the override.
        $this->audit->log([
            'tenant_id' => Auth::user()->tenant_id,
            'facility_id' => Auth::user()->facility_id ?? null,
            'user_id' => Auth::id(),
            'event_category' => 'SECURITY',
            'action' => 'BREAK_GLASS',
            'entity_type' => 'Patient',
            'entity_id' => $patient->id,
            'after_state' => json_encode(['justification' => $validated['justification']]),
        ]);

        // Store the override in the session — valid for 5 minutes.
        $request->session()->put('break_glass.patient_id', $patient->id);
        $request->session()->put('break_glass.expires_at', now()->addMinutes(5)->timestamp);

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Break-glass access granted. This session is audited and expires in 5 minutes.');
    }

    /**
     * Manually revoke an active break-glass session before it expires.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $patientId = $request->session()->get('break_glass.patient_id');

        if ($patientId && Auth::id()) {
            $this->audit->log([
                'tenant_id' => Auth::user()->tenant_id,
                'user_id' => Auth::id(),
                'event_category' => 'SECURITY',
                'action' => 'BREAK_GLASS_REVOKED',
                'entity_type' => 'Patient',
                'entity_id' => $patientId,
            ]);
        }

        $request->session()->forget(['break_glass.patient_id', 'break_glass.expires_at']);

        return back()->with('success', 'Break-glass access revoked.');
    }
}
