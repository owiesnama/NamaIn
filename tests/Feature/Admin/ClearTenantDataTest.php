<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsSuperAdmin();

    $this->tenant = Tenant::factory()->create(['is_active' => false]);
    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner']);

    app()->instance('currentTenant', $this->tenant);
});

test('cannot clear data for active tenant', function () {
    $this->tenant->update(['is_active' => true]);

    $this->put(route('admin.tenants.clear-data', $this->tenant), [
        'domain_confirmation' => $this->tenant->slug,
        'groups' => ['inventory'],
    ])->assertSessionHasErrors('tenant');
});

test('domain confirmation must match tenant slug', function () {
    $this->put(route('admin.tenants.clear-data', $this->tenant), [
        'domain_confirmation' => 'wrong-domain',
        'groups' => ['inventory'],
    ])->assertSessionHasErrors('domain_confirmation');
});

test('clearing inventory group soft-deletes products and related data', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $storage->addStock($product, 50, 'initial_stock', actor: $this->owner);

    $this->put(route('admin.tenants.clear-data', $this->tenant), [
        'domain_confirmation' => $this->tenant->slug,
        'groups' => ['inventory'],
    ])->assertRedirect();

    expect(Product::withoutGlobalScope(TenantScope::class)->where('tenant_id', $this->tenant->id)->count())->toBe(0);
    expect(Product::withoutGlobalScope(TenantScope::class)->onlyTrashed()->where('tenant_id', $this->tenant->id)->count())->toBe(1);

    $this->tenant->refresh();
    expect($this->tenant->data_cleared_at)->not->toBeNull();
    expect($this->tenant->cleared_groups)->toContain('inventory');
});

test('clearing commercial auto-selects inventory and financial', function () {
    Product::factory()->create(['tenant_id' => $this->tenant->id]);
    Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    Expense::factory()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->owner->id]);

    $this->put(route('admin.tenants.clear-data', $this->tenant), [
        'domain_confirmation' => $this->tenant->slug,
        'groups' => ['commercial'],
    ])->assertRedirect();

    $this->tenant->refresh();
    expect($this->tenant->cleared_groups)->toContain('commercial');
    expect($this->tenant->cleared_groups)->toContain('inventory');
    expect($this->tenant->cleared_groups)->toContain('financial');

    expect(Product::withoutGlobalScope(TenantScope::class)->where('tenant_id', $this->tenant->id)->count())->toBe(0);
    expect(Customer::withoutGlobalScope(TenantScope::class)->where('tenant_id', $this->tenant->id)->count())->toBe(0);
    expect(Expense::withoutGlobalScope(TenantScope::class)->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

test('restoring tenant data un-soft-deletes records', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->put(route('admin.tenants.clear-data', $this->tenant), [
        'domain_confirmation' => $this->tenant->slug,
        'groups' => ['inventory'],
    ]);

    expect(Product::withoutGlobalScope(TenantScope::class)->where('tenant_id', $this->tenant->id)->count())->toBe(0);

    $this->put(route('admin.tenants.restore-data', $this->tenant))->assertRedirect();

    expect(Product::withoutGlobalScope(TenantScope::class)->where('tenant_id', $this->tenant->id)->count())->toBe(1);

    $this->tenant->refresh();
    expect($this->tenant->data_cleared_at)->toBeNull();
    expect($this->tenant->cleared_groups)->toBeNull();
});

test('hard delete command processes tenants cleared over 30 days ago', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->tenant->update([
        'data_cleared_at' => now()->subDays(31),
        'cleared_groups' => ['inventory'],
    ]);

    Product::withoutGlobalScopes()->where('id', $product->id)->delete();

    $this->artisan('tenants:hard-delete-cleared')->assertSuccessful();

    expect(Product::withoutGlobalScope(TenantScope::class)->withTrashed()->where('tenant_id', $this->tenant->id)->count())->toBe(0);

    $this->tenant->refresh();
    expect($this->tenant->data_cleared_at)->toBeNull();
});

test('hard delete command skips tenants cleared less than 30 days ago', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->tenant->update([
        'data_cleared_at' => now()->subDays(10),
        'cleared_groups' => ['inventory'],
    ]);

    Product::withoutGlobalScopes()->where('id', $product->id)->delete();

    $this->artisan('tenants:hard-delete-cleared')->assertSuccessful();

    expect(Product::withoutGlobalScope(TenantScope::class)->withTrashed()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('users and roles are preserved after clearing all data', function () {
    Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->put(route('admin.tenants.clear-data', $this->tenant), [
        'domain_confirmation' => $this->tenant->slug,
        'groups' => ['commercial'],
    ]);

    expect($this->tenant->users()->count())->toBe(1);
    expect($this->tenant->fresh()->name)->toBe($this->tenant->name);
});
