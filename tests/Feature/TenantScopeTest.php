<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('models are automatically scoped to current tenant', function () {
    $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);

    $userA = User::factory()->create(['current_tenant_id' => $tenantA->id]);
    $tenantA->users()->attach($userA, ['role' => 'owner']);

    // Create customer as tenant A
    $this->actingAs($userA);
    $customerA = Customer::create(['name' => 'Customer A', 'tenant_id' => $tenantA->id]);
    $customerB = Customer::create(['name' => 'Customer B', 'tenant_id' => $tenantB->id]);

    // Only sees tenant A's customer
    expect(Customer::count())->toBe(1);
    expect(Customer::first()->name)->toBe('Customer A');
});

test('creating a model auto-assigns current tenant id', function () {
    $tenant = Tenant::create(['name' => 'T', 'slug' => 't']);
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'owner']);

    $this->actingAs($user);

    $customer = Customer::create(['name' => 'Auto Tenant']);

    expect($customer->tenant_id)->toBe($tenant->id);
});

test('user can switch tenant', function () {
    $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);
    $user = User::factory()->create(['current_tenant_id' => $tenantA->id]);
    $tenantA->users()->attach($user, ['role' => 'owner']);
    $tenantB->users()->attach($user, ['role' => 'member']);

    $user->switchTenant($tenantB);

    expect($user->fresh()->current_tenant_id)->toBe($tenantB->id);
});

test('user cannot switch to tenant they do not belong to', function () {
    $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);
    $user = User::factory()->create(['current_tenant_id' => $tenantA->id]);
    $tenantA->users()->attach($user, ['role' => 'owner']);

    $user->switchTenant($tenantB);
})->throws(DomainException::class);
