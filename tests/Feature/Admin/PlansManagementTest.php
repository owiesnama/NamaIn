<?php

use App\Features\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use Inertia\Testing\AssertableInertia as Assert;

it('lists plans for a super admin', function () {
    Plan::factory()->count(2)->create();
    actingAsSuperAdmin();

    $this->get('/__admin/plans')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Plans/Index')->has('plans', 2));
});

it('exposes the granted-feature count for each plan', function () {
    $plan = Plan::factory()->create();
    $plan->planFeatures()->createMany([
        ['feature_key' => Feature::Pos->value, 'value' => true],          // granted
        ['feature_key' => Feature::Bookings->value, 'value' => false],    // off, not counted
        ['feature_key' => Feature::MaxProducts->value, 'value' => 50],    // granted
        ['feature_key' => Feature::MaxUsers->value, 'value' => null],     // unlimited, granted
        ['feature_key' => Feature::MaxWarehouses->value, 'value' => 0],   // deny, not counted
    ]);
    actingAsSuperAdmin();

    $this->get('/__admin/plans')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Plans/Index')
            ->where('plans.0.features_count', 3));
});

it('creates a plan with coerced feature values', function () {
    actingAsSuperAdmin();

    $this->post('/__admin/plans', [
        'key' => 'gold',
        'name' => ['en' => 'Gold', 'ar' => 'ذهبي'],
        'is_active' => true,
        'sort' => 1,
        'features' => [
            Feature::Bookings->value => true,
            Feature::MaxProducts->value => 100,
        ],
    ])->assertRedirect(route('admin.plans.index'));

    $plan = Plan::where('key', 'gold')->firstOrFail();
    expect($plan->planFeatures()->where('feature_key', 'bookings')->value('value'))->toBeTrue();
    expect($plan->planFeatures()->where('feature_key', 'max_products')->value('value'))->toBe(100);
});

it('rejects an unknown feature key', function () {
    actingAsSuperAdmin();

    $this->post('/__admin/plans', [
        'key' => 'x',
        'name' => ['en' => 'X', 'ar' => 'X'],
        'features' => ['not_a_feature' => true],
    ])->assertSessionHasErrors('features.not_a_feature');
});

it('rejects a non-numeric limit value', function () {
    actingAsSuperAdmin();

    $this->post('/__admin/plans', [
        'key' => 'x',
        'name' => ['en' => 'X', 'ar' => 'X'],
        'features' => [Feature::MaxProducts->value => 'lots'],
    ])->assertSessionHasErrors('features.'.Feature::MaxProducts->value);
});

it('rejects a duplicate key', function () {
    Plan::factory()->create(['key' => 'taken']);
    actingAsSuperAdmin();

    $this->post('/__admin/plans', [
        'key' => 'taken',
        'name' => ['en' => 'X', 'ar' => 'X'],
    ])->assertSessionHasErrors('key');
});

it('replaces feature values on update', function () {
    $plan = Plan::factory()->create();
    $plan->planFeatures()->create(['feature_key' => Feature::Bookings->value, 'value' => true]);
    actingAsSuperAdmin();

    $this->put("/__admin/plans/{$plan->id}", [
        'key' => $plan->key,
        'name' => ['en' => 'Renamed', 'ar' => 'Renamed'],
        'features' => [Feature::Pos->value => true],
    ])->assertRedirect(route('admin.plans.index'));

    expect($plan->planFeatures()->where('feature_key', 'bookings')->exists())->toBeFalse();
    expect($plan->planFeatures()->where('feature_key', 'pos')->value('value'))->toBeTrue();
});

it('clears the previous default when a new default is set', function () {
    $previous = Plan::factory()->default()->create();
    actingAsSuperAdmin();

    $this->post('/__admin/plans', [
        'key' => 'new-default',
        'name' => ['en' => 'New', 'ar' => 'New'],
        'is_default' => true,
    ])->assertRedirect();

    expect($previous->fresh()->is_default)->toBeFalse();
    expect(Plan::where('is_default', true)->count())->toBe(1);
});

it('blocks deleting a plan that has subscriptions', function () {
    $plan = Plan::factory()->create();
    Subscription::factory()->create(['plan_id' => $plan->id]);
    actingAsSuperAdmin();

    $this->delete("/__admin/plans/{$plan->id}")->assertSessionHas('error');

    expect(Plan::whereKey($plan->id)->exists())->toBeTrue();
});

it('deletes a plan without subscriptions', function () {
    $plan = Plan::factory()->create();
    actingAsSuperAdmin();

    $this->delete("/__admin/plans/{$plan->id}")->assertRedirect(route('admin.plans.index'));

    expect(Plan::whereKey($plan->id)->exists())->toBeFalse();
});

it('denies a non-admin user', function () {
    actingAsTenantUser();

    $this->get('/__admin/plans')->assertRedirect('/__admin/login');
});
