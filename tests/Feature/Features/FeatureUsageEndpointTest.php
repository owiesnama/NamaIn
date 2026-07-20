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

it('returns usage and cap for a limit feature', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::MaxProducts->value => 10]);
    Product::factory()->count(3)->create();
    Entitlements::flush();

    $this->getJson('/features/max_products/usage')
        ->assertOk()
        ->assertJson([
            'feature' => 'max_products',
            'used' => 3,
            'limit' => 10,
            'remaining' => 7,
        ]);
});

it('reports null cap as unlimited', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::MaxProducts->value => null]);

    $this->getJson('/features/max_products/usage')
        ->assertOk()
        ->assertJson(['limit' => null, 'remaining' => null]);
});

it('404s for a boolean feature', function () {
    actingAsTenantUser();

    $this->getJson('/features/quotes/usage')->assertNotFound();
});

it('404s for an unknown feature key', function () {
    actingAsTenantUser();

    $this->getJson('/features/not_a_feature/usage')->assertNotFound();
});
