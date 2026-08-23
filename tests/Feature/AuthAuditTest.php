<?php

use App\Domains\Audit\Models\AuditLog;

test('a successful login is recorded on the audit trail', function () {
    $env = $this->setupTenantEnvironment();

    $this->post(route('login'), [
        'email' => $env['user']->email,
        'password' => 'password123',
    ])->assertRedirect();

    $log = AuditLog::where('entity_id', $env['user']->id)->where('action', 'LOGIN')->first();

    expect($log)->not->toBeNull()
        ->and($log->event_category)->toBe('AUTH')
        ->and($log->tenant_id)->toBe($env['tenant']->id);
});

test('a logout is recorded on the audit trail', function () {
    $env = $this->setupTenantEnvironment();
    $this->actingAs($env['user']);

    $this->post(route('logout'))->assertRedirect();

    $log = AuditLog::where('entity_id', $env['user']->id)->where('action', 'LOGOUT')->first();

    expect($log)->not->toBeNull()
        ->and($log->event_category)->toBe('AUTH');
});

test('a failed login against a real account is recorded as a security event', function () {
    $env = $this->setupTenantEnvironment();

    $this->post(route('login'), [
        'email' => $env['user']->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors();

    $log = AuditLog::where('entity_id', $env['user']->id)->where('action', 'LOGIN_FAILED')->first();

    expect($log)->not->toBeNull()
        ->and($log->event_category)->toBe('SECURITY');
});

test('a deactivated account cannot log in even with the correct password', function () {
    $env = $this->setupTenantEnvironment();
    $env['user']->update(['status' => 'deactivated']);

    $this->post(route('login'), [
        'email' => $env['user']->email,
        'password' => 'password123',
    ])->assertSessionHasErrors();

    $this->assertGuest();
});

test('a failed login against an unknown email does not error', function () {
    $this->setupTenantEnvironment();

    $this->post(route('login'), [
        'email' => 'nobody@nowhere.local',
        'password' => 'whatever',
    ])->assertSessionHasErrors();

    expect(AuditLog::where('action', 'LOGIN_FAILED')->count())->toBe(0);
});
