<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::where('slug', 'test-org')->first();
    seedTenantRoles($this->tenant);
    $this->ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $this->staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
});

it('allows an active user to access tenant routes', function () {
    actingAsTenantUser(role: 'owner')
        ->get(route('dashboard'))
        ->assertOk();
});

it('logs out and redirects an inactive user with an error message', function () {
    $user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($user, [
        'role' => 'staff',
        'role_id' => $this->staffRole->id,
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');
});

it('passes through for unauthenticated users without interfering', function () {
    $this->get(route('dashboard'))
        ->assertRedirect();

    $this->assertGuest();
});

it('rejects access when the subdomain tenant is inactive even if user current tenant is active', function () {
    $activeTenant = $this->tenant; // test-org is active
    $inactiveTenant = Tenant::create(['name' => 'Inactive Org', 'slug' => 'inactive-org', 'is_active' => false]);

    $user = User::factory()->create(['current_tenant_id' => $activeTenant->id]);
    $activeTenant->users()->attach($user, [
        'role' => 'owner',
        'role_id' => $this->ownerRole->id,
        'is_active' => true,
    ]);
    $inactiveTenant->users()->attach($user, [
        'role' => 'staff',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    // Visit the inactive tenant's subdomain — ResolveTenant will bind
    // the inactive tenant as app('currentTenant')
    $inactiveUrl = 'http://inactive-org.'.config('app.domain').'/dashboard';

    $this->get($inactiveUrl)
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');
});

it('allows an active user whose status is re-enabled', function () {
    $user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($user, [
        'role' => 'staff',
        'role_id' => $this->staffRole->id,
        'is_active' => false,
    ]);

    // Enable the user
    $this->tenant->users()->updateExistingPivot($user->id, ['is_active' => true]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});
