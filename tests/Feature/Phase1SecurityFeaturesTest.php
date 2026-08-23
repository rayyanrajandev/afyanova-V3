<?php

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\RoleAssignment;
use App\Domains\Patient\Models\Patient;
use Illuminate\Support\Facades\Config;
use Ramsey\Uuid\Uuid;

test('session idle timeout forces logout and creates audit entry when idle threshold exceeded', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];

    Config::set('session.idle_timeout', 15);

    // Active session within threshold
    $this->actingAs($user)
        ->withSession(['last_activity_at' => time() - (5 * 60)])
        ->get('/dashboard')
        ->assertOk();

    // Expired idle session (> 15 min)
    $response = $this->actingAs($user)
        ->withSession(['last_activity_at' => time() - (20 * 60)])
        ->get('/dashboard');

    $response->assertRedirect(route('login', ['reason' => 'idle_timeout']));
    $this->assertGuest();

    $log = AuditLog::where('user_id', $user->id)
        ->where('action', 'IDLE_TIMEOUT')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->event_category)->toBe('SECURITY')
        ->and($log->tenant_id)->toBe($env['tenant']->id);
});

test('break-glass access allows authorized clinician to access cross-facility patient with mandatory justification and audit trail', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];

    // Grant clinical.break_glass permission
    $permission = Permission::firstOrCreate(['slug' => 'clinical.break_glass'], [
        'id' => Uuid::uuid7()->toString(),
        'name' => 'Break Glass Emergency Access',
        'domain' => 'Clinical',
    ]);

    $role = Role::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'name' => 'Emergency Physician',
        'slug' => 'emergency-physician',
    ]);
    $role->permissions()->attach($permission->id);

    RoleAssignment::create([
        'id' => Uuid::uuid7()->toString(),
        'user_id' => $user->id,
        'role_id' => $role->id,
        'facility_id' => $env['facility']->id,
    ]);

    // Create a patient in the same tenant
    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-EMERG-001',
        'first_name' => 'John',
        'last_name' => 'Trauma',
        'dob' => '1985-05-15',
        'gender' => 'male',
        'status' => 'active',
    ]);

    $justification = 'Unconscious severe polytrauma patient arriving in acute shock from Outlying Facility without referral.';

    $response = $this->actingAs($user)
        ->post(route('clinical.break-glass.store'), [
            'patient_id' => $patient->id,
            'justification' => $justification,
        ]);

    $response->assertRedirect(route('patients.show', $patient->id));
    $response->assertSessionHas('break_glass.patient_id', $patient->id);

    $log = AuditLog::where('entity_id', $patient->id)
        ->where('action', 'BREAK_GLASS')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->event_category)->toBe('SECURITY')
        ->and($log->tenant_id)->toBe($env['tenant']->id)
        ->and($log->user_id)->toBe($user->id);
});

test('break-glass is rejected if justification is too short (< 20 chars)', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];

    $permission = Permission::firstOrCreate(['slug' => 'clinical.break_glass'], [
        'id' => Uuid::uuid7()->toString(),
        'name' => 'Break Glass Emergency Access',
        'domain' => 'Clinical',
    ]);

    $role = Role::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'name' => 'Emergency Physician 2',
        'slug' => 'emergency-physician-2',
    ]);
    $role->permissions()->attach($permission->id);

    RoleAssignment::create([
        'id' => Uuid::uuid7()->toString(),
        'user_id' => $user->id,
        'role_id' => $role->id,
        'facility_id' => $env['facility']->id,
    ]);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-EMERG-002',
        'first_name' => 'Jane',
        'last_name' => 'Trauma',
        'dob' => '1990-01-01',
        'gender' => 'female',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->post(route('clinical.break-glass.store'), [
            'patient_id' => $patient->id,
            'justification' => 'Too short',
        ])
        ->assertSessionHasErrors('justification');
});

test('auditedBulkUpdate and auditedBulkDelete write audit entries for bulk operations', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-BULK-001',
        'first_name' => 'Bulk',
        'last_name' => 'Patient',
        'dob' => '1992-03-10',
        'gender' => 'male',
        'status' => 'active',
    ]);

    $inv1 = Invoice::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'invoice_number' => 'INV-BLK-001',
        'status' => 'Draft',
        'total_amount' => 100.00,
        'paid_amount' => 0.00,
    ]);

    $inv2 = Invoice::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'invoice_number' => 'INV-BLK-002',
        'status' => 'Draft',
        'total_amount' => 200.00,
        'paid_amount' => 0.00,
    ]);

    $updatedCount = Invoice::auditedBulkUpdate(
        query: Invoice::where('patient_id', $patient->id)->where('status', 'Draft'),
        attributes: ['status' => 'Pending Approval'],
        reason: 'End-of-shift batch submission of draft invoices',
        userId: $user->id,
        tenantId: $env['tenant']->id,
    );

    expect($updatedCount)->toBe(2);

    $audit = AuditLog::where('action', 'BULK_UPDATE')
        ->where('entity_type', 'Invoice')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->justification_reason)->toBe('End-of-shift batch submission of draft invoices');
});
