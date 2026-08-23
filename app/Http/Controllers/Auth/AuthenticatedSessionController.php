<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * LoginRequest::authenticate() verifies the password and, for an
     * account with TOTP confirmed, stops short of a real session and
     * parks the pending user id in auth.mfa_pending_user_id instead of
     * logging in — see that method for why. This just branches on
     * whether that happened.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $pendingMfa = $request->session()->has('auth.mfa_pending_user_id');

        $request->session()->regenerate();

        if ($pendingMfa) {
            return redirect()->route('two-factor.login');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
