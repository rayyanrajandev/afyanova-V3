<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\TwoFactorAuthenticationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post-login TOTP MFA challenge.
 *
 * Flow:
 *  1. LoginRequest::authenticate() verifies the password without ever
 *     calling Auth::login() — for an account with TOTP confirmed, it
 *     parks the user id (and remember-me flag) under
 *     auth.mfa_pending_user_id / auth.mfa_pending_remember and returns,
 *     and AuthenticatedSessionController::store() redirects here.
 *  2. create() renders the challenge page with the pending user's email
 *     (for display only — never expose the raw ID to the frontend).
 *  3. store() accepts either a TOTP code or a recovery code, rate-limits
 *     the attempt, and — on success — calls Auth::loginUsingId() to
 *     establish the full Laravel session (the first and only Login event
 *     fired for this sign-in), then clears the pending keys and redirects
 *     to the dashboard.
 */
class TwoFactorChallengeController extends Controller
{
    /**
     * Show the two-factor challenge prompt.
     * Redirects to /login if no pending challenge is in the session.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $pendingId = $request->session()->get('auth.mfa_pending_user_id');

        if (! $pendingId || ! ($user = User::find($pendingId))) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            // Safe to expose — it is only the masked email shown in the UI.
            'maskedEmail' => $this->maskEmail($user->email),
        ]);
    }

    /**
     * Validate the submitted TOTP code or recovery code and complete login.
     *
     * @throws ValidationException
     */
    public function store(Request $request, TwoFactorAuthenticationService $mfa, AuditLogger $audit): RedirectResponse
    {
        $pendingId = $request->session()->get('auth.mfa_pending_user_id');

        if (! $pendingId || ! ($user = User::find($pendingId))) {
            return redirect()->route('login');
        }

        // Rate-limit: 5 attempts per IP per minute to prevent brute-force.
        $key = 'two-factor.'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $audit->log([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'event_category' => 'SECURITY',
                'action' => 'MFA_CHALLENGE_RATE_LIMITED',
                'entity_type' => 'User',
                'entity_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Please wait before trying again.',
            ]);
        }

        $validated = $request->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $code = trim($validated['code'] ?? '');
        $recoveryCode = trim($validated['recovery_code'] ?? '');

        $verified = false;

        if ($recoveryCode !== '') {
            $verified = $mfa->verifyRecoveryCode($user, $recoveryCode);
        } elseif ($code !== '') {
            $verified = $mfa->verify($user, $code);
        }

        if (! $verified) {
            RateLimiter::hit($key, 60);

            $audit->log([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'event_category' => 'SECURITY',
                'action' => 'MFA_CHALLENGE_FAILED',
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'after_state' => json_encode(['method' => $recoveryCode !== '' ? 'recovery_code' : 'totp']),
            ]);

            throw ValidationException::withMessages([
                'code' => 'The provided two-factor code is invalid.',
            ]);
        }

        // Success — clear rate limiter, flush the pending keys, establish session.
        RateLimiter::clear($key);
        $remember = $request->session()->get('auth.mfa_pending_remember', false);
        $request->session()->forget(['auth.mfa_pending_user_id', 'auth.mfa_pending_remember']);

        $audit->log([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'event_category' => 'AUTH',
            'action' => 'MFA_CHALLENGE_SUCCESS',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'after_state' => json_encode(['method' => $recoveryCode !== '' ? 'recovery_code' : 'totp']),
        ]);

        Auth::loginUsingId($user->id, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $masked = Str::limit($local, 2, '').str_repeat('*', max(0, strlen($local) - 2));

        return $masked.'@'.$domain;
    }
}
