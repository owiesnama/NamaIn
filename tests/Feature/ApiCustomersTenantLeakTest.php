<?php

use App\Models\Customer;
use App\Models\Tenant;

it('rejects unauthenticated requests to the customers API', function () {
    $response = $this->getJson(route('api.customers.index'));

    $response->assertUnauthorized();
});

it('returns only customers belonging to the authenticated user tenant', function () {
    actingAsTenantUser();

    $tenant = app('currentTenant');
    Customer::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    $otherTenant = Tenant::create(['name' => 'Other Org', 'slug' => 'other-org', 'is_active' => true]);
    Customer::factory()->count(2)->create(['tenant_id' => $otherTenant->id]);

    $response = $this->getJson(route('api.customers.index'))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

it('excludes system customers from the API response', function () {
    actingAsTenantUser();

    $tenant = app('currentTenant');
    Customer::factory()->create(['tenant_id' => $tenant->id, 'is_system' => true]);
    Customer::factory()->create(['tenant_id' => $tenant->id, 'is_system' => false]);

    $response = $this->getJson(route('api.customers.index'))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});
