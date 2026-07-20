<?php

use App\Features\Facades\Entitlements;
use App\Features\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\Route;

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

it('attaches the feature middleware to each gated route', function (string $routeName, string $middleware) {
    $route = Route::getRoutes()->getByName($routeName);

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain($middleware);
})->with([
    'pos' => ['pos.index', 'feature:pos'],
    'multi-warehouse' => ['stock-transfers.index', 'feature:multi_warehouse'],
    'cheques' => ['cheques.index', 'feature:cheques'],
    'exports' => ['exports.index', 'feature:exports'],
    'advanced reports' => ['reports.index', 'feature:advanced_reports'],
    'quotes' => ['quotes.index', 'feature:quotes'],
]);

it('blocks a gated module when the plan excludes it', function (string $feature, string $url) {
    actingAsTenantUser();
    subscribeCurrentTenant([$feature => false]);

    $this->get($url)->assertStatus(403);
})->with([
    'pos' => [Feature::Pos->value, '/pos'],
    'multi-warehouse' => [Feature::MultiWarehouse->value, '/stock-transfers'],
    'cheques' => [Feature::Cheques->value, '/cheques'],
    'exports' => [Feature::Exports->value, '/exports'],
]);

it('lets a gated module through when the plan includes it', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::Pos->value => true]);

    expect($this->get('/pos')->status())->not->toBe(403);
});

it('grants everything while no plan is configured (permissive)', function () {
    actingAsTenantUser(); // no subscription, no default plan

    expect($this->get('/pos')->status())->not->toBe(403);
});
