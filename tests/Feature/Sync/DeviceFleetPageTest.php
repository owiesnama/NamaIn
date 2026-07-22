<?php

use App\Actions\Sync\EnrollDeviceAction;
use App\Actions\Sync\ProvisionDeviceAction;
use App\Enums\StorageType;
use App\Models\Role;
use App\Models\Storage;
use App\Models\User;

function fleetOwner(): User
{
    $tenant = app('currentTenant');
    seedTenantRoles($tenant);
    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'owner')->first();
    $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);
    app('auth')->forgetGuards();

    return $owner;
}

it('denies the fleet dashboard to users without devices.view', function () {
    $tenant = app('currentTenant');
    seedTenantRoles($tenant);
    $cashierRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'cashier')->first();
    $cashier = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($cashier, ['role' => 'cashier', 'role_id' => $cashierRole->id, 'is_active' => true]);

    $this->actingAs($cashier)->get(route('devices.index'))->assertForbidden();
});

it('shows the fleet dashboard to an owner with health', function () {
    $storage = Storage::create(['name' => 'Front Store', 'address' => 'x', 'type' => StorageType::SALE_POINT]);
    $enrollment = app(EnrollDeviceAction::class)->handle($storage, 'Front counter');
    app(ProvisionDeviceAction::class)->handle($enrollment['pairing_code']);

    $this->actingAs(fleetOwner())
        ->get(route('devices.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Devices/Index')
            ->has('devices', 1)
            ->where('devices.0.health', 'offline'));
});
