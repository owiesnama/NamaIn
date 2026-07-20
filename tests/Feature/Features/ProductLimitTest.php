<?php

use App\Features\Facades\Entitlements;
use App\Features\Feature;
use App\Models\Plan;
use App\Models\Product;
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

it('blocks creating a product once the plan cap is reached', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::MaxProducts->value => 2]);
    Product::factory()->count(2)->create();
    Entitlements::flush();

    $this->post('/products', ['name' => 'Another', 'cost' => 5])
        ->assertSessionHasErrors('name');

    expect(Product::count())->toBe(2);
});

it('allows creating a product below the cap', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::MaxProducts->value => 50]);
    Entitlements::flush();

    $this->post('/products', ['name' => 'New', 'cost' => 5])
        ->assertSessionHasNoErrors();
});

it('allows unlimited product creation when the cap is null', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::MaxProducts->value => null]);
    Product::factory()->count(5)->create();
    Entitlements::flush();

    $this->post('/products', ['name' => 'New', 'cost' => 5])
        ->assertSessionHasNoErrors();
});

it('does not apply the product cap when updating an existing product', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::MaxProducts->value => 1]);
    $product = Product::factory()->create();
    Entitlements::flush();

    // Already at the cap (1 product), but updating must still be allowed.
    $this->put("/products/{$product->id}", ['name' => 'Renamed', 'cost' => 9])
        ->assertSessionHasNoErrors();
});
