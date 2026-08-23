<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Identity\Services\TwoFactorAuthenticationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service TOTP enrollment for the authenticated user's own account.
 * Enrollment is two steps on purpose: store() only generates a pending
 * secret, confirm() only activates it once a real code from the
 * authenticator app proves the user actually has the device set up.
 */
class TwoFactorAuthenticationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request, TwoFactorAuthenticationService $service): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/TwoFactorAuthentication', [
            'enabled' => $service->isEnabled($user),
            'qrCodeUrl' => $user->two_factor_secret && ! $service->isEnabled($user)
                ? $service->qrCodeUrl($user)
                : null,
        ]);
    }

    public function store(Request $request, TwoFactorAuthenticationService $service): RedirectResponse
    {
        $service->enable($request->user());

        return back()->with('status', 'two-factor-authentication-pending');
    }

    public function confirm(Request $request, TwoFactorAuthenticationService $service): RedirectResponse
    {
        $validated = $request->validate(['code' => 'required|string']);

        $recoveryCodes = $service->confirm($request->user(), $validated['code']);

        if (! $recoveryCodes) {
            $this->audit->log([
                'tenant_id' => $request->user()->tenant_id,
                'user_id' => $request->user()->id,
                'event_category' => 'SECURITY',
                'action' => 'MFA_CONFIRMATION_FAILED',
                'entity_type' => 'User',
                'entity_id' => $request->user()->id,
            ]);

            throw ValidationException::withMessages([
                'code' => 'The provided two-factor code was invalid.',
            ]);
        }

        $this->audit->log([
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'event_category' => 'SECURITY',
            'action' => 'MFA_ENABLED',
            'entity_type' => 'User',
            'entity_id' => $request->user()->id,
        ]);

        return back()->with([
            'status' => 'two-factor-authentication-confirmed',
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    public function destroy(Request $request, TwoFactorAuthenticationService $service): RedirectResponse
    {
        $user = $request->user();
        $service->disable($user);

        $this->audit->log([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'event_category' => 'SECURITY',
            'action' => 'MFA_DISABLED',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);

        return back()->with('status', 'two-factor-authentication-disabled');
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorAuthenticationService $service): RedirectResponse
    {
        abort_unless($service->isEnabled($request->user()), 400);

        $user = $request->user();
        $codes = $service->regenerateRecoveryCodes($user);

        $this->audit->log([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'event_category' => 'SECURITY',
            'action' => 'MFA_RECOVERY_CODES_REGENERATED',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);

        return back()->with(['status' => 'recovery-codes-generated', 'recoveryCodes' => $codes]);
    }
}
