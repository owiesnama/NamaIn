<?php

use App\Features\Feature;
use App\Models\AdminAuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use Inertia\Testing\AssertableInertia as Assert;

it('assigns a plan to a tenant and leaves one live subscription', function () {
    $tenant = Tenant::factory()->create();
    $free = Plan::factory()->create();
    $pro = Plan::factory()->create();
    Subscription::factory()->active()->create(['tenant_id' => $tenant->id, 'plan_id' => $free->id]);
    actingAsSuperAdmin();

    $this->put("/__admin/tenants/{$tenant->id}/subscription", ['plan_id' => $pro->id])
        ->assertRedirect();

    expect($tenant->subscriptions()->whereIn('status', ['active', 'trialing'])->count())->toBe(1);
    expect($tenant->currentSubscription()->plan_id)->toBe($pro->id);
    expect(AdminAuditLog::where('action', 'tenant.plan_assigned')->exists())->toBeTrue();
});

it('stores a feature override on the target tenant, not the acting context', function () {
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin();

    $this->post("/__admin/tenants/{$tenant->id}/overrides", [
        'feature_key' => Feature::Bookings->value,
        'value' => '1',
    ])->assertRedirect();

    $override = TenantFeatureOverride::where('feature_key', 'bookings')->firstOrFail();
    expect($override->tenant_id)->toBe($tenant->id);
    expect($override->value)->toBeTrue();
});

it('upserts an override over an expired one', function () {
    $tenant = Tenant::factory()->create();
    TenantFeatureOverride::factory()->forFeature(Feature::Bookings, false)->expired()->create(['tenant_id' => $tenant->id]);
    actingAsSuperAdmin();

    $this->post("/__admin/tenants/{$tenant->id}/overrides", [
        'feature_key' => Feature::Bookings->value,
        'value' => '1',
    ])->assertRedirect();

    expect(TenantFeatureOverride::where('tenant_id', $tenant->id)->where('feature_key', 'bookings')->count())->toBe(1);
    expect(TenantFeatureOverride::where('tenant_id', $tenant->id)->where('feature_key', 'bookings')->value('value'))->toBeTrue();
});

it('removes an override', function () {
    $tenant = Tenant::factory()->create();
    TenantFeatureOverride::factory()->forFeature(Feature::Bookings, true)->create(['tenant_id' => $tenant->id]);
    actingAsSuperAdmin();

    $this->delete("/__admin/tenants/{$tenant->id}/overrides/bookings")->assertRedirect();

    expect(TenantFeatureOverride::where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

it('shows the subscription panel data including the entitlements preview', function () {
    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create();
    $plan->planFeatures()->create(['feature_key' => Feature::Bookings->value, 'value' => true]);
    Subscription::factory()->active()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id]);
    actingAsSuperAdmin();

    $this->get("/__admin/tenants/{$tenant->id}")->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Tenants/Show')
        ->where('subscription.plan_id', $plan->id)
        ->has('plans')
        ->has('entitlements', count(Feature::cases()))
        ->where('entitlements.0.source', fn ($source) => in_array($source, ['override', 'plan', 'default'], true))
    );
});
