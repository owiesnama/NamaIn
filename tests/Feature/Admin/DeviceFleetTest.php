<?php

use App\Models\AdminAuditLog;
use App\Models\Tenant;

it('lists tenants on the super-admin device fleet page', function () {
    actingAsSuperAdmin();
    Tenant::create(['name' => 'Pilot Store', 'slug' => 'pilot-store', 'is_active' => true, 'offline_enabled' => true]);

    test()->get(route('admin.device-fleet.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Admin/DeviceFleet/Index')->has('tenants.data'));
});

it('toggles a tenant offline flag and audits it', function () {
    actingAsSuperAdmin();
    $tenant = Tenant::create(['name' => 'Pilot Store', 'slug' => 'pilot-store', 'is_active' => true, 'offline_enabled' => false]);

    test()->put(route('admin.device-fleet.offline', $tenant))->assertRedirect();

    expect($tenant->fresh()->isOfflineEnabled())->toBeTrue();
    expect(AdminAuditLog::where('action', 'tenant.offline_enabled')->where('target_id', $tenant->id)->exists())->toBeTrue();

    // Toggling again disables and audits the reverse action.
    test()->put(route('admin.device-fleet.offline', $tenant))->assertRedirect();
    expect($tenant->fresh()->isOfflineEnabled())->toBeFalse();
    expect(AdminAuditLog::where('action', 'tenant.offline_disabled')->where('target_id', $tenant->id)->exists())->toBeTrue();
});

it('denies the fleet page to non-admins', function () {
    test()->get(route('admin.device-fleet.index'))->assertRedirect();
});
