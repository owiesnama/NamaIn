<?php

use App\Enums\StorageType;
use App\Features\Facades\Entitlements;
use App\Features\Feature;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Storage;
use App\Models\Subscription;

if (! function_exists('subscribeCurrentTenant')) {
    /** @param array<string, bool|int|null> $features */
    function subscribeCurrentTenant(array $features): void
    {
        $tenant = app('currentTenant');
        $plan = Plan::factory()->create();

        foreach ($features as $key => $value) {
            $plan->planFeatures()->create(['feature_key' => $key, 'value' => $value]);
        }

        Subscription::factory()->active()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id]);
        Entitlements::flush($tenant);
    }
}

it('blocks creating a warehouse once the plan cap is reached', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::MaxWarehouses->value => 1]);
    Storage::factory()->create();
    Entitlements::flush();

    $this->post('/storages', ['name' => 'Second', 'address' => 'X', 'type' => StorageType::WAREHOUSE->value])
        ->assertSessionHasErrors('name');
});

it('allows creating a warehouse below the cap', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::MaxWarehouses->value => 5]);
    Entitlements::flush();

    $this->post('/storages', ['name' => 'First', 'address' => 'X', 'type' => StorageType::WAREHOUSE->value])
        ->assertSessionHasNoErrors();
});

it('blocks inviting a user once the seat cap is reached', function () {
    actingAsTenantUser(); // seeds the owner user (1 seat used)
    subscribeCurrentTenant([Feature::MaxUsers->value => 1]);
    Entitlements::flush();

    $staffRole = Role::withoutGlobalScopes()
        ->where('tenant_id', app('currentTenant')->id)
        ->where('slug', 'staff')
        ->first();

    $this->post('/users/invite', ['email' => 'new@example.com', 'role_id' => $staffRole->id])
        ->assertSessionHasErrors('email');
});

it('allows inviting a user below the seat cap', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::MaxUsers->value => 10]);
    Entitlements::flush();

    $staffRole = Role::withoutGlobalScopes()
        ->where('tenant_id', app('currentTenant')->id)
        ->where('slug', 'staff')
        ->first();

    $this->post('/users/invite', ['email' => 'new@example.com', 'role_id' => $staffRole->id])
        ->assertSessionHasNoErrors();
});
