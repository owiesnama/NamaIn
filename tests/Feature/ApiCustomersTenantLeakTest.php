<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects unauthenticated requests to the customers API', function () {
    $this->withoutTenantSubdomain()
        ->getJson('/api/customers')
        ->assertUnauthorized();
});

it('returns only customers belonging to the authenticated user tenant', function () {
    $tenant = Tenant::where('slug', 'test-org')->first();

    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'owner', 'is_active' => true]);

    Customer::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    $otherTenant = Tenant::create(['name' => 'Other Org', 'slug' => 'other-org', 'is_active' => true]);
    Customer::factory()->count(2)->create(['tenant_id' => $otherTenant->id]);

    Sanctum::actingAs($user);

    $response = $this->withoutTenantSubdomain()
        ->getJson('/api/customers')
        ->assertOk();

    expect($response->json())->toHaveCount(3);
});

it('excludes system customers from the API response', function () {
    $tenant = Tenant::where('slug', 'test-org')->first();

    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'owner', 'is_active' => true]);

    Customer::factory()->create(['tenant_id' => $tenant->id, 'is_system' => true]);
    Customer::factory()->create(['tenant_id' => $tenant->id, 'is_system' => false]);

    Sanctum::actingAs($user);

    $response = $this->withoutTenantSubdomain()
        ->getJson('/api/customers')
        ->assertOk();

    expect($response->json())->toHaveCount(1);
});
