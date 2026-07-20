<?php

use App\Features\Facades\Entitlements;
use App\Features\Feature;
use App\Http\Middleware\EnsureFeatureIsActive;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

it('allows a full-page GET when the feature is enabled', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::Quotes->value => true]);

    $this->get('/quotes')->assertOk();
});

it('renders the upgrade page on a full-page GET when the feature is disabled', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::Quotes->value => false]);

    $this->get('/quotes')
        ->assertStatus(403)
        ->assertInertia(fn (Assert $page) => $page->component('Upgrade')->where('feature', __('features.quotes')));
});

it('returns a 403 for a write request when the feature is disabled', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::Quotes->value => false]);

    $this->post('/quotes', ['name' => 'Q'])->assertStatus(403);
});

it('returns a plain 403 (not a full page) for an Inertia partial reload when disabled', function () {
    actingAsTenantUser();
    subscribeCurrentTenant([Feature::Quotes->value => false]);

    // Exercised at the middleware directly: a real HTTP partial reload would be
    // short-circuited by Inertia's asset-version check before reaching here.
    $request = Request::create('/quotes', 'GET');
    $request->headers->set('X-Inertia', 'true');
    $request->headers->set('X-Inertia-Partial-Data', 'quotes');

    $status = null;
    try {
        (new EnsureFeatureIsActive)->handle($request, fn ($r) => response('ok'), Feature::Quotes->value);
    } catch (HttpException $e) {
        $status = $e->getStatusCode();
    }

    expect($status)->toBe(403);
});

it('rejects a limit feature key at the middleware', function () {
    $middleware = new EnsureFeatureIsActive;

    $middleware->handle(
        Request::create('/x', 'GET'),
        fn ($request) => response('ok'),
        Feature::MaxProducts->value,
    );
})->throws(InvalidArgumentException::class);
