<?php

namespace App\Http\Requests\Auth;

use App\Domains\Identity\Services\TwoFactorAuthenticationService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Verify the request's credentials and, for an account without MFA
     * confirmed, complete the login.
     *
     * Deliberately does not call Auth::attempt() — that fires the Login
     * event immediately, and an account with TOTP confirmed must clear a
     * second factor before a real session exists. Calling Auth::attempt()
     * and then Auth::logout() to "undo" it (the previous approach here)
     * still leaves a LOGIN followed by a LOGOUT on the audit trail for a
     * login that hasn't actually happened yet, which reads as a spurious
     * sign-in/sign-out pair to anyone reviewing it. Verifying credentials
     * by hand through the same guard provider Auth::attempt() itself uses
     * gets the same rate-limiting and Failed-event behavior on a wrong
     * password, without ever firing Login before MFA is satisfied.
     * TwoFactorChallengeController is the only place Login then fires for
     * an MFA-gated account, once the second factor actually checks out.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $provider = Auth::guard('web')->getProvider();
        $user = $provider->retrieveByCredentials($this->only('email'));

        if (! $user || ! $provider->validateCredentials($user, $this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            event(new Failed('web', $user, $this->only('email', 'password')));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Case-insensitive status check to support 'active', 'Active', 'ACTIVE' across all DB engines (PostgreSQL, SQLite, MySQL)
        if (strtolower($user->status ?? 'active') !== 'active') {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'This user account has been suspended or deactivated. Please contact your hospital administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if (app(TwoFactorAuthenticationService::class)->isEnabled($user)) {
            $this->session()->put('auth.mfa_pending_user_id', $user->getAuthIdentifier());
            $this->session()->put('auth.mfa_pending_remember', $this->boolean('remember'));

            return;
        }

        Auth::guard('web')->login($user, $this->boolean('remember'));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
