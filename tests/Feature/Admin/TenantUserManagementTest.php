<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DefaultRolesService;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    (new PermissionSeeder)->run();

    $this->managedTenant = Tenant::factory()->create();
    (new DefaultRolesService)->seedForTenant($this->managedTenant);

    $ownerRole = Role::withoutGlobalScopes()
        ->where('tenant_id', $this->managedTenant->id)
        ->where('slug', 'owner')
        ->first();

    $this->tenantOwner = User::factory()->create();
    $this->managedTenant->users()->attach($this->tenantOwner->id, [
        'role' => 'owner',
        'role_id' => $ownerRole->id,
        'is_active' => true,
    ]);
});

it('shows tenant detail with members', function () {
    actingAsSuperAdmin();

    $this->get(route('admin.tenants.show', $this->managedTenant))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tenants/Show')
            ->has('members', 1)
            ->has('roles')
        );
});

it('adds a user to a tenant', function () {
    actingAsSuperAdmin();

    $staffRole = Role::withoutGlobalScopes()
        ->where('tenant_id', $this->managedTenant->id)
        ->where('slug', 'staff')
        ->first();

    $this->post(route('admin.tenants.users.store', $this->managedTenant), [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'role_id' => $staffRole->id,
    ])->assertRedirect();

    expect($this->managedTenant->users()->where('users.email', 'newuser@example.com')->exists())->toBeTrue();
});

it('changes a user role within a tenant', function () {
    actingAsSuperAdmin();

    $member = User::factory()->create();
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->managedTenant->id)->where('slug', 'staff')->first();
    $managerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->managedTenant->id)->where('slug', 'manager')->first();

    $this->managedTenant->users()->attach($member->id, [
        'role' => 'staff',
        'role_id' => $staffRole->id,
        'is_active' => true,
    ]);

    $this->put(route('admin.tenants.users.role', [$this->managedTenant, $member]), [
        'role_id' => $managerRole->id,
    ])->assertRedirect();

    $pivot = $this->managedTenant->users()->where('users.id', $member->id)->first()->pivot;
    expect($pivot->role)->toBe('manager');
});

it('rejects roles from another tenant', function () {
    actingAsSuperAdmin();

    $otherTenant = Tenant::factory()->create();
    (new DefaultRolesService)->seedForTenant($otherTenant);

    $otherRole = Role::withoutGlobalScopes()->where('tenant_id', $otherTenant->id)->where('slug', 'staff')->first();

    $member = User::factory()->create();
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->managedTenant->id)->where('slug', 'staff')->first();
    $this->managedTenant->users()->attach($member->id, [
        'role' => 'staff',
        'role_id' => $staffRole->id,
        'is_active' => true,
    ]);

    $this->put(route('admin.tenants.users.role', [$this->managedTenant, $member]), [
        'role_id' => $otherRole->id,
    ])->assertSessionHasErrors('role_id');
});

it('cannot remove the last owner', function () {
    actingAsSuperAdmin();

    $this->delete(route('admin.tenants.users.destroy', [$this->managedTenant, $this->tenantOwner]))
        ->assertSessionHasErrors('user');

    expect($this->managedTenant->users()->where('users.id', $this->tenantOwner->id)->exists())->toBeTrue();
});

it('toggles user active status', function () {
    actingAsSuperAdmin();

    $member = User::factory()->create();
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->managedTenant->id)->where('slug', 'staff')->first();
    $this->managedTenant->users()->attach($member->id, [
        'role' => 'staff',
        'role_id' => $staffRole->id,
        'is_active' => true,
    ]);

    $this->put(route('admin.tenants.users.status', [$this->managedTenant, $member]))
        ->assertRedirect();

    $pivot = $this->managedTenant->users()->where('users.id', $member->id)->first()->pivot;
    expect((bool) $pivot->is_active)->toBeFalse();
});

it('removes a non-owner user from tenant', function () {
    actingAsSuperAdmin();

    $member = User::factory()->create(['current_tenant_id' => $this->managedTenant->id]);
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->managedTenant->id)->where('slug', 'staff')->first();
    $this->managedTenant->users()->attach($member->id, [
        'role' => 'staff',
        'role_id' => $staffRole->id,
        'is_active' => true,
    ]);

    $this->delete(route('admin.tenants.users.destroy', [$this->managedTenant, $member]))
        ->assertRedirect();

    expect($this->managedTenant->users()->where('users.id', $member->id)->exists())->toBeFalse();
    $member->refresh();
    expect($member->current_tenant_id)->toBeNull();
});

it('transfers ownership', function () {
    actingAsSuperAdmin();

    $newOwner = User::factory()->create();
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->managedTenant->id)->where('slug', 'staff')->first();
    $this->managedTenant->users()->attach($newOwner->id, [
        'role' => 'staff',
        'role_id' => $staffRole->id,
        'is_active' => true,
    ]);

    $this->put(route('admin.tenants.ownership', $this->managedTenant), [
        'user_id' => $newOwner->id,
    ])->assertRedirect();

    $owner = $this->managedTenant->owner();
    expect($owner->id)->toBe($newOwner->id);

    $oldOwnerPivot = $this->managedTenant->users()->where('users.id', $this->tenantOwner->id)->first()->pivot;
    expect($oldOwnerPivot->role)->toBe('manager');
});
