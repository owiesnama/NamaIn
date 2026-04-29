<?php

use App\Models\Tenant;
use App\Models\User;

it('lists tenants with pagination', function () {
    Tenant::factory()->count(3)->create();

    actingAsSuperAdmin();

    $this->get(route('admin.tenants.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tenants/Index')
            ->has('tenants.data', 4) // 3 + 1 from beforeEach
        );
});

it('filters tenants by search', function () {
    Tenant::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme-corp']);
    Tenant::factory()->create(['name' => 'Other Inc', 'slug' => 'other-inc']);

    actingAsSuperAdmin();

    $this->get(route('admin.tenants.index', ['search' => 'Acme']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tenants.data', 1)
        );
});

it('creates a tenant with an owner', function () {
    $owner = User::factory()->create();
    $owner->markEmailAsVerified();

    actingAsSuperAdmin();

    $this->post(route('admin.tenants.store'), [
        'name' => 'New Tenant',
        'slug' => 'new-tenant',
        'owner_email' => $owner->email,
    ])->assertRedirect();

    $this->assertDatabaseHas('tenants', ['name' => 'New Tenant', 'slug' => 'new-tenant']);

    $tenant = Tenant::where('slug', 'new-tenant')->first();
    expect($tenant->owner())->not->toBeNull();
    expect($tenant->owner()->id)->toBe($owner->id);
});

it('rejects duplicate slugs', function () {
    Tenant::factory()->create(['slug' => 'taken']);

    actingAsSuperAdmin();

    $this->post(route('admin.tenants.store'), [
        'name' => 'Dup',
        'slug' => 'taken',
        'owner_email' => User::factory()->create()->email,
    ])->assertSessionHasErrors('slug');
});

it('rejects reserved slugs', function () {
    actingAsSuperAdmin();

    $this->post(route('admin.tenants.store'), [
        'name' => 'Admin Tenant',
        'slug' => 'admin',
        'owner_email' => User::factory()->create()->email,
    ])->assertSessionHasErrors('slug');
});

it('updates a tenant', function () {
    $tenant = Tenant::factory()->create(['name' => 'Old Name', 'slug' => 'old-slug']);

    actingAsSuperAdmin();

    $this->put(route('admin.tenants.update', $tenant), [
        'name' => 'New Name',
        'slug' => 'new-slug',
    ])->assertRedirect();

    $tenant->refresh();
    expect($tenant->name)->toBe('New Name');
    expect($tenant->slug)->toBe('new-slug');
});

it('toggles tenant active status', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);

    actingAsSuperAdmin();

    $this->put(route('admin.tenants.status', $tenant))->assertRedirect();

    $tenant->refresh();
    expect($tenant->is_active)->toBeFalse();

    $this->put(route('admin.tenants.status', $tenant))->assertRedirect();

    $tenant->refresh();
    expect($tenant->is_active)->toBeTrue();
});

it('deletes a deactivated tenant', function () {
    $tenant = Tenant::factory()->create(['is_active' => false]);

    actingAsSuperAdmin();

    $this->delete(route('admin.tenants.destroy', $tenant))
        ->assertRedirect(route('admin.tenants.index'));

    $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
});

it('cannot delete an active tenant', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);

    actingAsSuperAdmin();

    $this->delete(route('admin.tenants.destroy', $tenant))
        ->assertSessionHasErrors('tenant');

    $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
});

it('returns 403 for non-admin on all operations', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->markEmailAsVerified();

    $this->actingAs($user, 'admin');

    $this->get(route('admin.tenants.index'))->assertForbidden();
    $this->post(route('admin.tenants.store'), [])->assertForbidden();
    $this->get(route('admin.tenants.show', 1))->assertForbidden();
});
