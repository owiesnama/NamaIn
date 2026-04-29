<?php

use App\Models\Product;
use App\Models\Tenant;

beforeEach(function () {
    // Remove the tenant context set by the global Pest beforeEach
    app()->forgetInstance('currentTenant');
    auth()->logout();
});

test('tenant scope returns no records when no tenant is bound', function () {
    $tenant = Tenant::create(['name' => 'Org A', 'slug' => 'org-a', 'is_active' => true]);

    Product::withoutGlobalScopes()->create([
        'name' => 'Widget',
        'cost' => 50,
        'tenant_id' => $tenant->id,
    ]);

    expect(Product::count())->toBe(0);
});

test('tenant scope returns records when tenant is bound', function () {
    $tenant = Tenant::create(['name' => 'Org B', 'slug' => 'org-b', 'is_active' => true]);

    app()->instance('currentTenant', $tenant);

    Product::withoutGlobalScopes()->create([
        'name' => 'Widget',
        'cost' => 50,
        'tenant_id' => $tenant->id,
    ]);

    expect(Product::count())->toBe(1);
});

test('tenant scope isolates data between tenants', function () {
    $tenantA = Tenant::create(['name' => 'Org A', 'slug' => 'org-a', 'is_active' => true]);
    $tenantB = Tenant::create(['name' => 'Org B', 'slug' => 'org-b', 'is_active' => true]);

    Product::withoutGlobalScopes()->create([
        'name' => 'A Widget',
        'cost' => 50,
        'tenant_id' => $tenantA->id,
    ]);

    Product::withoutGlobalScopes()->create([
        'name' => 'B Widget',
        'cost' => 100,
        'tenant_id' => $tenantB->id,
    ]);

    app()->instance('currentTenant', $tenantA);
    expect(Product::count())->toBe(1);
    expect(Product::first()->name)->toBe('A Widget');
});
