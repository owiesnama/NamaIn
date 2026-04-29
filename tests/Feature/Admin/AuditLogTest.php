<?php

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\User;

it('logs tenant creation', function () {
    $owner = User::factory()->create();
    $owner->markEmailAsVerified();

    actingAsSuperAdmin();

    $this->post(route('admin.tenants.store'), [
        'name' => 'Audit Test',
        'slug' => 'audit-test',
        'owner_email' => $owner->email,
    ]);

    $log = AdminAuditLog::where('action', 'tenant.created')->first();
    expect($log)->not->toBeNull();
    expect($log->metadata['owner_email'])->toBe($owner->email);
});

it('logs tenant update', function () {
    $tenant = Tenant::factory()->create(['name' => 'Before', 'slug' => 'before']);

    actingAsSuperAdmin();

    $this->put(route('admin.tenants.update', $tenant), [
        'name' => 'After',
        'slug' => 'after',
    ]);

    $log = AdminAuditLog::where('action', 'tenant.updated')->first();
    expect($log)->not->toBeNull();
    expect($log->metadata['old_slug'])->toBe('before');
});

it('logs tenant deletion', function () {
    $tenant = Tenant::factory()->create(['is_active' => false, 'name' => 'To Delete']);

    actingAsSuperAdmin();

    $this->delete(route('admin.tenants.destroy', $tenant));

    $log = AdminAuditLog::where('action', 'tenant.deleted')->first();
    expect($log)->not->toBeNull();
    expect($log->metadata['name'])->toBe('To Delete');
});

it('stores admin user id and request metadata', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->markEmailAsVerified();

    $owner = User::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.tenants.store'), [
        'name' => 'Meta Test',
        'slug' => 'meta-test',
        'owner_email' => $owner->email,
    ]);

    $log = AdminAuditLog::where('action', 'tenant.created')->first();
    expect($log->admin_user_id)->toBe($admin->id);
    expect($log->ip_address)->not->toBeNull();
});
