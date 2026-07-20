<?php

use App\Features\Facades\Entitlements;
use App\Features\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use Inertia\Testing\AssertableInertia as Assert;

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

it('shares effective entitlements for a tenant user', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([
        Feature::Quotes->value => true,
        Feature::Pos->value => false,
        Feature::MaxProducts->value => 50,
        Feature::MaxUsers->value => null,
    ]);

    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('entitlements.features.quotes', true)
        ->where('entitlements.features.pos', false)
        ->where('entitlements.limits.max_products', 50)
        ->where('entitlements.limits.max_users', null)
    );
});

it('exposes a boolean for every boolean feature and a cap for every limit', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::Quotes->value => true]);

    $this->get('/dashboard')->assertInertia(function (Assert $page) {
        foreach (Feature::booleans() as $feature) {
            $page->has("entitlements.features.{$feature->value}");
        }
        foreach (Feature::limits() as $feature) {
            $page->has("entitlements.limits.{$feature->value}");
        }
    });
});
