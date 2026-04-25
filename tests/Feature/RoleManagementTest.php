<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::where('slug', 'test-org')->first();
    seedTenantRoles($this->tenant);
});

it('allows owner to view roles index', function () {
    actingAsTenantUser(role: 'owner')
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Roles/Index'));
});

it('denies staff from viewing roles index', function () {
    actingAsTenantUser(role: 'staff')
        ->get(route('roles.index'))
        ->assertForbidden();
});

it('allows owner to create a custom role', function () {
    $permission = Permission::first();

    actingAsTenantUser(role: 'owner')
        ->post(route('roles.store'), [
            'name' => 'Accountant',
            'slug' => 'accountant',
            'permission_ids' => [$permission->id],
        ])
        ->assertRedirect();

    $role = Role::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('slug', 'accountant')
        ->first();

    expect($role)->not->toBeNull();
    expect($role->permissions->contains($permission->id))->toBeTrue();
});

it('scopes roles to the current tenant', function () {
    $otherTenant = Tenant::create(['name' => 'Other Org', 'slug' => 'other-org', 'is_active' => true]);
    seedTenantRoles($otherTenant);

    actingAsTenantUser(role: 'owner')
        ->get(route('roles.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Roles/Index')
            ->where('roles', fn ($roles) => collect($roles)->every(
                fn ($r) => Role::withoutGlobalScopes()->find($r['id'])?->tenant_id === $this->tenant->id
            ))
        );
});

it('allows owner to update a custom role', function () {
    $role = Role::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Reviewer',
        'slug' => 'reviewer',
        'is_system' => false,
    ]);

    $permission = Permission::first();

    actingAsTenantUser(role: 'owner')
        ->put(route('roles.update', $role), [
            'name' => 'Senior Reviewer',
            'slug' => 'reviewer',
            'permission_ids' => [$permission->id],
        ])
        ->assertRedirect();

    expect($role->fresh()->name)->toBe('Senior Reviewer');
});

it('cannot update a system role name', function () {
    $systemRole = Role::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('is_system', true)
        ->first();

    actingAsTenantUser(role: 'owner')
        ->put(route('roles.update', $systemRole), [
            'name' => 'Hacked Name',
            'slug' => $systemRole->slug,
            'permission_ids' => [],
        ])
        ->assertSessionHasErrors();
});

it('allows owner to delete a custom role', function () {
    $role = Role::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Temporary',
        'slug' => 'temporary',
        'is_system' => false,
    ]);

    actingAsTenantUser(role: 'owner')
        ->delete(route('roles.destroy', $role))
        ->assertRedirect();

    expect(Role::withoutGlobalScopes()->find($role->id))->toBeNull();
});

it('cannot delete a system role', function () {
    $systemRole = Role::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('is_system', true)
        ->first();

    actingAsTenantUser(role: 'owner')
        ->delete(route('roles.destroy', $systemRole))
        ->assertSessionHasErrors();
});
