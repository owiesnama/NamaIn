<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DefaultRolesService;
use Database\Seeders\PermissionSeeder;

/*
 * These tests exercise the full middleware stack WITHOUT pre-binding
 * tenant context. They validate that ResolveTenant, EnsureTenantIsActive,
 * and EnsureUserIsActiveInTenant work correctly end-to-end.
 */

function tenantUrl(string $slug, string $path = '/dashboard'): string
{
    return 'http://'.$slug.'.'.config('app.domain').$path;
}

function setupTenantWithUser(string $slug, string $name, bool $isActive = true, string $role = 'owner'): array
{
    $tenant = Tenant::create(['name' => $name, 'slug' => $slug, 'is_active' => $isActive]);
    (new PermissionSeeder)->run();
    (new DefaultRolesService)->seedForTenant($tenant);

    $user = User::factory()->create([
        'current_tenant_id' => $tenant->id,
    ]);
    $user->markEmailAsVerified();

    $roleModel = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', $role)->first();
    $tenant->users()->attach($user, ['role' => $role, 'role_id' => $roleModel?->id, 'is_active' => true]);

    return [$tenant, $user];
}

it('resolves a valid tenant from subdomain and renders the page', function () {
    [$tenant, $user] = setupTenantWithUser('acme', 'Acme Corp');

    $this->actingAs($user)
        ->get(tenantUrl('acme'))
        ->assertOk();
});

it('returns 404 for an unknown tenant subdomain', function () {
    [$tenant, $user] = setupTenantWithUser('acme', 'Acme Corp');

    $this->actingAs($user)
        ->get(tenantUrl('nonexistent'))
        ->assertNotFound();
});

it('redirects unauthenticated users to login', function () {
    Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);

    $this->get(tenantUrl('acme'))
        ->assertRedirect();
});

it('rejects access to an inactive tenant subdomain', function () {
    [$tenant, $user] = setupTenantWithUser('dormant', 'Dormant Corp', isActive: false);

    $this->actingAs($user)
        ->get(tenantUrl('dormant'))
        ->assertRedirect(route('login'));
});

it('blocks a user who does not belong to the subdomain tenant', function () {
    // Create tenant A and user
    [$tenantA, $userA] = setupTenantWithUser('alpha', 'Alpha Corp');

    // Create tenant B (user is NOT attached)
    Tenant::create(['name' => 'Beta Corp', 'slug' => 'beta', 'is_active' => true]);

    $this->actingAs($userA)
        ->get(tenantUrl('beta'))
        ->assertForbidden();
});

it('allows a user who belongs to multiple tenants to access each', function () {
    [$tenantA, $user] = setupTenantWithUser('alpha', 'Alpha Corp');

    $tenantB = Tenant::create(['name' => 'Beta Corp', 'slug' => 'beta', 'is_active' => true]);
    (new DefaultRolesService)->seedForTenant($tenantB);
    $roleModel = Role::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->where('slug', 'staff')->first();
    $tenantB->users()->attach($user, ['role' => 'staff', 'role_id' => $roleModel?->id, 'is_active' => true]);

    $this->actingAs($user)
        ->get(tenantUrl('beta'))
        ->assertOk();
});
