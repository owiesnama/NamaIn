<?php

use App\Actions\ProvisionTenantAction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it provisions a tenant with permissions, roles, defaults, and an active owner', function () {
    Permission::query()->delete(); // provisioning must not rely on pre-seeded permissions

    $owner = User::factory()->create(['current_tenant_id' => null]);

    $tenant = app(ProvisionTenantAction::class)->handle('Acme Corp', 'Acme-Corp', $owner);

    expect($tenant->slug)->toBe('acme-corp')
        ->and((bool) $tenant->is_active)->toBeTrue()
        ->and(Permission::count())->toBeGreaterThan(0)
        ->and(Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'owner')->exists())->toBeTrue();

    $pivot = $tenant->users()->where('users.id', $owner->id)->first()->pivot;

    expect($pivot->role)->toBe('owner')
        ->and((bool) $pivot->is_active)->toBeTrue()
        ->and($pivot->role_id)->not->toBeNull()
        ->and($owner->fresh()->current_tenant_id)->toBe($tenant->id);
});

test('it does not steal the owner\'s current tenant when one is already set', function () {
    $owner = User::factory()->create(['current_tenant_id' => app('currentTenant')->id]);

    app(ProvisionTenantAction::class)->handle('Second Org', 'second-org', $owner);

    expect($owner->fresh()->current_tenant_id)->toBe(app('currentTenant')->id);
});

test('provisioning is atomic: a failure while attaching the owner leaves nothing behind', function () {
    $owner = User::factory()->create();
    $rolesBefore = Role::withoutGlobalScopes()->count();

    $owner->delete(); // the attach hits a foreign key violation after all the seeding ran

    expect(fn () => app(ProvisionTenantAction::class)->handle('Ghost Co', 'ghost-co', $owner))
        ->toThrow(QueryException::class);

    expect(Tenant::where('slug', 'ghost-co')->exists())->toBeFalse()
        ->and(Role::withoutGlobalScopes()->count())->toBe($rolesBefore);
});
