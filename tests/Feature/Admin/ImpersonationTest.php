<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DefaultRolesService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    (new PermissionSeeder)->run();

    $this->impersonatedTenant = Tenant::factory()->create(['name' => 'Impersonated Corp']);
    (new DefaultRolesService)->seedForTenant($this->impersonatedTenant);

    $ownerRole = Role::withoutGlobalScopes()
        ->where('tenant_id', $this->impersonatedTenant->id)
        ->where('slug', 'owner')
        ->first();

    $this->tenantOwner = User::factory()->create(['current_tenant_id' => $this->impersonatedTenant->id]);
    $this->impersonatedTenant->users()->attach($this->tenantOwner->id, [
        'role' => 'owner',
        'role_id' => $ownerRole->id,
        'is_active' => true,
    ]);
});

it('starts impersonation for a specific user and stores session keys', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->markEmailAsVerified();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.impersonate.start', [$this->impersonatedTenant, $this->tenantOwner]))
        ->assertRedirect();

    expect(session('impersonating_from'))->toBe($admin->id);
    expect(session('impersonating_user_id'))->toBe($this->tenantOwner->id);
    expect(session('impersonating_tenant_id'))->toBe($this->impersonatedTenant->id);
    expect(session('impersonating_tenant_name'))->toBe('Impersonated Corp');
});

it('can impersonate a non-owner user', function () {
    $staffRole = Role::withoutGlobalScopes()
        ->where('tenant_id', $this->impersonatedTenant->id)
        ->where('slug', 'staff')
        ->first();

    $staffUser = User::factory()->create();
    $this->impersonatedTenant->users()->attach($staffUser->id, [
        'role' => 'staff',
        'role_id' => $staffRole->id,
        'is_active' => true,
    ]);

    actingAsSuperAdmin();

    $this->post(route('admin.impersonate.start', [$this->impersonatedTenant, $staffUser]))
        ->assertRedirect();

    expect(session('impersonating_user_id'))->toBe($staffUser->id);
});

it('cannot impersonate a user not in the tenant', function () {
    $outsider = User::factory()->create();

    actingAsSuperAdmin();

    $this->post(route('admin.impersonate.start', [$this->impersonatedTenant, $outsider]))
        ->assertSessionHasErrors('user');
});

it('cannot nest impersonation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->markEmailAsVerified();

    $this->actingAs($admin, 'admin')
        ->withSession(['impersonating_from' => 999])
        ->post(route('admin.impersonate.start', [$this->impersonatedTenant, $this->tenantOwner]))
        ->assertSessionHasErrors('impersonation');
});

it('stops impersonation and restores admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->markEmailAsVerified();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.impersonate.start', [$this->impersonatedTenant, $this->tenantOwner]));

    URL::defaults([]);

    $this->actingAs($this->tenantOwner)
        ->withSession([
            'impersonating_from' => $admin->id,
            'impersonating_user_id' => $this->tenantOwner->id,
            'impersonating_tenant_id' => $this->impersonatedTenant->id,
            'impersonating_tenant_name' => $this->impersonatedTenant->name,
        ])
        ->post(route('admin.impersonate.stop'))
        ->assertRedirect(route('admin.dashboard'));
});

it('non-admin cannot impersonate', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->markEmailAsVerified();

    $this->actingAs($user, 'admin')
        ->post(route('admin.impersonate.start', [$this->impersonatedTenant, $this->tenantOwner]))
        ->assertForbidden();
});

it('sets current_tenant_id on the impersonated user', function () {
    $otherTenant = Tenant::factory()->create();
    $this->tenantOwner->update(['current_tenant_id' => $otherTenant->id]);

    actingAsSuperAdmin();

    $this->post(route('admin.impersonate.start', [$this->impersonatedTenant, $this->tenantOwner]));

    $this->tenantOwner->refresh();
    expect($this->tenantOwner->current_tenant_id)->toBe($this->impersonatedTenant->id);
    expect(session('impersonating_previous_tenant_id'))->toBe($otherTenant->id);
});

it('restores user current_tenant_id when stopping', function () {
    $otherTenant = Tenant::factory()->create();
    $this->tenantOwner->update(['current_tenant_id' => $otherTenant->id]);

    $admin = User::factory()->create(['role' => 'admin']);
    $admin->markEmailAsVerified();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.impersonate.start', [$this->impersonatedTenant, $this->tenantOwner]));

    $this->tenantOwner->refresh();
    expect($this->tenantOwner->current_tenant_id)->toBe($this->impersonatedTenant->id);

    URL::defaults([]);

    $this->actingAs($this->tenantOwner)
        ->withSession([
            'impersonating_from' => $admin->id,
            'impersonating_user_id' => $this->tenantOwner->id,
            'impersonating_tenant_id' => $this->impersonatedTenant->id,
            'impersonating_previous_tenant_id' => $otherTenant->id,
        ])
        ->post(route('admin.impersonate.stop'));

    $this->tenantOwner->refresh();
    expect($this->tenantOwner->current_tenant_id)->toBe($otherTenant->id);
});

it('logs audit events for impersonation start and stop', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->markEmailAsVerified();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.impersonate.start', [$this->impersonatedTenant, $this->tenantOwner]));

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_user_id' => $admin->id,
        'action' => 'impersonation.started',
    ]);

    URL::defaults([]);

    $this->actingAs($this->tenantOwner)
        ->withSession([
            'impersonating_from' => $admin->id,
            'impersonating_user_id' => $this->tenantOwner->id,
            'impersonating_tenant_id' => $this->impersonatedTenant->id,
        ])
        ->post(route('admin.impersonate.stop'));

    $this->assertDatabaseHas('admin_audit_logs', [
        'admin_user_id' => $admin->id,
        'action' => 'impersonation.stopped',
    ]);
});
