<?php

use App\Models\AdminAuditLog;
use App\Models\User;

it('shows the activity log page', function () {
    actingAsSuperAdmin();

    $this->get(route('admin.activity'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/ActivityLog')
            ->has('logs')
            ->has('actions')
        );
});

it('lists audit log entries', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    AdminAuditLog::create([
        'admin_user_id' => $admin->id,
        'action' => 'tenant.created',
        'metadata' => ['name' => 'Test Tenant'],
        'ip_address' => '127.0.0.1',
    ]);

    actingAsSuperAdmin($admin);

    $this->get(route('admin.activity'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
        );
});

it('filters by action type', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    AdminAuditLog::create(['admin_user_id' => $admin->id, 'action' => 'tenant.created']);
    AdminAuditLog::create(['admin_user_id' => $admin->id, 'action' => 'impersonation.started']);

    actingAsSuperAdmin($admin);

    $this->get(route('admin.activity', ['action' => 'impersonation.started']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'impersonation.started')
        );
});

it('searches by admin name', function () {
    $admin1 = User::factory()->create(['role' => 'admin', 'name' => 'Alice Admin']);
    $admin2 = User::factory()->create(['role' => 'admin', 'name' => 'Bob Admin']);

    AdminAuditLog::create(['admin_user_id' => $admin1->id, 'action' => 'tenant.created']);
    AdminAuditLog::create(['admin_user_id' => $admin2->id, 'action' => 'tenant.created']);

    actingAsSuperAdmin($admin1);

    $this->get(route('admin.activity', ['search' => 'Alice']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
        );
});
