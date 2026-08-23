<?php

use App\Domains\Identity\Services\TwoFactorAuthenticationService;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

function currentTotpCode(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

test('a user can start enrollment and receives a pending, unconfirmed secret', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $this->post(route('two-factor.enable'))
        ->assertSessionHas('status', 'two-factor-authentication-pending');

    $user = $env['user']->fresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

test('confirming with a valid TOTP code activates MFA and issues recovery codes', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    app(TwoFactorAuthenticationService::class)->enable($user);
    $code = currentTotpCode($user->fresh()->two_factor_secret);

    $this->put(route('two-factor.confirm'), ['code' => $code])
        ->assertSessionHas('status', 'two-factor-authentication-confirmed');

    $user = $user->fresh();

    expect($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->two_factor_recovery_codes)->toHaveCount(8);
});

test('confirming with an invalid code is rejected and MFA stays inactive', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    app(TwoFactorAuthenticationService::class)->enable($user);

    $this->put(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('logging in with MFA enabled stops short of a session and redirects to the challenge', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $service = app(TwoFactorAuthenticationService::class);
    $service->enable($user);
    $service->confirm($user, currentTotpCode($user->fresh()->two_factor_secret));

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('the correct TOTP code at the challenge completes login', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $service = app(TwoFactorAuthenticationService::class);
    $service->enable($user);
    $service->confirm($user, currentTotpCode($user->fresh()->two_factor_secret));

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password123',
    ]);
    $this->assertGuest();

    $response = $this->post(route('two-factor.login.post'), [
        'code' => currentTotpCode($user->fresh()->two_factor_secret),
    ]);

    $this->assertAuthenticatedAs($user->fresh());
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('a wrong code at the challenge is rejected and the user stays unauthenticated', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $service = app(TwoFactorAuthenticationService::class);
    $service->enable($user);
    $service->confirm($user, currentTotpCode($user->fresh()->two_factor_secret));

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $this->post(route('two-factor.login.post'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a recovery code logs the user in and is consumed after one use', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $service = app(TwoFactorAuthenticationService::class);
    $service->enable($user);
    $recoveryCodes = $service->confirm($user, currentTotpCode($user->fresh()->two_factor_secret));
    $firstCode = $recoveryCodes[0];

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $this->post(route('two-factor.login.post'), ['recovery_code' => $firstCode])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user->fresh());
    expect($user->fresh()->two_factor_recovery_codes)->not->toContain($firstCode);

    // The same recovery code cannot be reused on a second login.
    Auth::logout();
    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $this->post(route('two-factor.login.post'), ['recovery_code' => $firstCode])
        ->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('disabling two-factor authentication clears enrollment and skips the challenge on next login', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $service = app(TwoFactorAuthenticationService::class);
    $service->enable($user);
    $service->confirm($user, currentTotpCode($user->fresh()->two_factor_secret));

    $this->actingAs($user)->delete(route('two-factor.disable'))
        ->assertSessionHas('status', 'two-factor-authentication-disabled');

    $user = $user->fresh();
    expect($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->two_factor_secret)->toBeNull();

    Auth::logout();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user->fresh());
});
