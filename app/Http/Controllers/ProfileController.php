<?php

namespace App\Http\Controllers;

use App\Domains\Identity\Services\TwoFactorAuthenticationService;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form, including MFA enrollment state.
     */
    public function edit(Request $request, TwoFactorAuthenticationService $mfa): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            // MFA props
            'mfaEnabled' => $mfa->isEnabled($user),
            'qrCodeUrl' => ($user->two_factor_secret && ! $mfa->isEnabled($user))
                ? $mfa->qrCodeUrl($user)
                : null,
            'recoveryCodes' => session('recoveryCodes'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        if (isset($validated['name'])) {
            $nameParts = explode(' ', $validated['name'], 2);
            $validated['first_name'] = $nameParts[0];
            $validated['last_name'] = $nameParts[1] ?? '';
            unset($validated['name']);
        }

        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Deactivate the user's account.
     *
     * This deliberately deactivates rather than hard-deletes: a clinical
     * or financial audit trail may already reference this user as the
     * actor on a signed note, a payment, a dispense — deleting the row
     * outright would either break that history's referential integrity or
     * (worse) let someone sever their own accountability by self-deleting
     * after the fact. Deactivation preserves the record and the trail
     * while ending the account's ability to log in.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->update(['status' => 'deactivated']);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
