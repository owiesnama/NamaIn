<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Tests\MainDomainTestCase;

// ── 404 ──────────────────────────────────────────────────────────────────────

it('renders the Inertia Error page for a missing main-domain URL', function () {
    $response = test()->withoutTenantSubdomain()->get('/this-route-does-not-exist-xyz');

    $response->assertStatus(404);
    $response->assertInertia(fn ($page) => $page
        ->component('Error')
        ->where('status', 404)
    );
})->uses(MainDomainTestCase::class);

it('renders the Inertia Error page for a missing tenant URL', function () {
    $response = $this->get('/this-route-does-not-exist-xyz');

    $response->assertStatus(404);
    $response->assertInertia(fn ($page) => $page
        ->component('Error')
        ->where('status', 404)
    );
});

// ── 403 ──────────────────────────────────────────────────────────────────────

it('renders the Inertia Error page for a forbidden tenant route', function () {
    $tenant = Tenant::where('slug', 'test-org')->first();
    seedTenantRoles($tenant);

    // Staff role does not have roles.manage permission
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $staffRole = Role::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('slug', 'staff')
        ->first();
    $tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);
    $user->refresh();

    $response = $this->actingAs($user)->get(route('roles.index'));

    $response->assertStatus(403);
    $response->assertInertia(fn ($page) => $page
        ->component('Error')
        ->where('status', 403)
    );
});

// ── JSON / API passthrough ────────────────────────────────────────────────────

it('does not return an Inertia Error page for a JSON 404 request', function () {
    $response = test()->withoutTenantSubdomain()
        ->getJson('/this-route-does-not-exist-xyz');

    $response->assertStatus(404);
    $response->assertJsonStructure(['message']);
    $this->assertFalse($response->headers->has('X-Inertia'));
})->uses(MainDomainTestCase::class);
