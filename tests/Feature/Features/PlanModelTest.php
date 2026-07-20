<?php

use App\Features\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Scopes\TenantScope;
use Illuminate\Database\QueryException;

it('does not apply the tenant scope to billing models', function () {
    expect((new Subscription)->hasGlobalScope(TenantScope::class))->toBeFalse();
    expect((new TenantFeatureOverride)->hasGlobalScope(TenantScope::class))->toBeFalse();
    expect((new Plan)->hasGlobalScope(TenantScope::class))->toBeFalse();
});

it('reads subscriptions across tenants regardless of current tenant', function () {
    // A currentTenant ("Test Org") is bound by the Feature beforeEach.
    Subscription::factory()->count(2)->create();

    expect(Subscription::count())->toBe(2);
});

it('does not auto-fill the current tenant onto an override', function () {
    $current = app('currentTenant');
    $other = Tenant::factory()->create();

    $override = TenantFeatureOverride::factory()->forFeature(Feature::Bookings, true)->create([
        'tenant_id' => $other->id,
    ]);

    expect($override->tenant_id)->toBe($other->id);
    expect($override->tenant_id)->not->toBe($current->id);
});

it('resolves a localized display name', function () {
    $plan = Plan::factory()->create(['name' => ['en' => 'Pro', 'ar' => 'احترافي']]);

    expect($plan->displayName('en'))->toBe('Pro');
    expect($plan->displayName('ar'))->toBe('احترافي');
    expect($plan->displayName('fr'))->toBe('Pro'); // falls back to English
});

it('exposes plan feature and subscription relations', function () {
    $plan = Plan::factory()->create();
    $plan->planFeatures()->create(['feature_key' => Feature::Bookings->value, 'value' => true]);
    Subscription::factory()->active()->create(['plan_id' => $plan->id]);

    expect($plan->planFeatures)->toHaveCount(1);
    expect($plan->subscriptions)->toHaveCount(1);
});

it('allows at most one default plan', function () {
    Plan::factory()->default()->create();

    Plan::factory()->default()->create();
})->throws(QueryException::class);

it('blocks deleting a plan that has subscriptions', function () {
    $plan = Plan::factory()->create();
    Subscription::factory()->create(['plan_id' => $plan->id]);

    $plan->delete();
})->throws(QueryException::class);
