<?php

use App\Actions\Subscriptions\AssignPlanToTenant;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

it('assigns a plan and leaves exactly one live subscription', function () {
    $tenant = Tenant::factory()->create();
    $free = Plan::factory()->create();
    $pro = Plan::factory()->create();
    $action = new AssignPlanToTenant;

    $first = $action->handle($tenant, $free);
    $second = $action->handle($tenant, $pro);

    expect($tenant->subscriptions()->whereIn('status', ['active', 'trialing'])->count())->toBe(1);
    expect($tenant->currentSubscription()->plan_id)->toBe($pro->id);
    expect($first->fresh()->status)->toBe(SubscriptionStatus::Canceled);
    expect($first->fresh()->ends_at)->not->toBeNull();
    expect($second->status)->toBe(SubscriptionStatus::Active);
});

it('starts a trial when a trial end date is given', function () {
    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create();

    $subscription = (new AssignPlanToTenant)->handle($tenant, $plan, now()->addDays(7));

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing);
    expect($subscription->trial_ends_at)->not->toBeNull();
    expect($tenant->currentSubscription()->id)->toBe($subscription->id);
});

it('excludes a trialing subscription whose trial has expired', function () {
    $tenant = Tenant::factory()->create();
    Subscription::factory()->expiredTrial()->create(['tenant_id' => $tenant->id]);

    expect($tenant->currentSubscription())->toBeNull();
});

it('excludes a subscription whose end date has passed', function () {
    $tenant = Tenant::factory()->create();
    Subscription::factory()->expired()->create(['tenant_id' => $tenant->id]);

    expect($tenant->currentSubscription())->toBeNull();
});

it('excludes a subscription ending exactly now', function () {
    $tenant = Tenant::factory()->create();
    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => SubscriptionStatus::Active,
        'ends_at' => now(),
    ]);

    expect($tenant->currentSubscription())->toBeNull();
});

it('returns the default plan when no live subscription exists', function () {
    $default = Plan::factory()->default()->create();
    $tenant = Tenant::factory()->create();

    expect($tenant->activePlan()->id)->toBe($default->id);
});

it('prefers the subscribed plan over the default plan', function () {
    Plan::factory()->default()->create();
    $subscribed = Plan::factory()->create();
    $tenant = Tenant::factory()->create();
    Subscription::factory()->active()->create(['tenant_id' => $tenant->id, 'plan_id' => $subscribed->id]);

    expect($tenant->activePlan()->id)->toBe($subscribed->id);
});
