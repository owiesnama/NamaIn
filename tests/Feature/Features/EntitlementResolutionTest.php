<?php

use App\Features\Exceptions\NoTenantContextException;
use App\Features\Facades\Entitlements;
use App\Features\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Models\User;
use Illuminate\Support\Facades\DB;

if (! function_exists('entPlanWith')) {
    /** @param array<string, bool|int|null> $features */
    function entPlanWith(array $features, bool $default = false): Plan
    {
        $plan = Plan::factory()->state(['is_default' => $default])->create();

        foreach ($features as $key => $value) {
            $plan->planFeatures()->create(['feature_key' => $key, 'value' => $value]);
        }

        return $plan;
    }
}

if (! function_exists('entSubscribe')) {
    function entSubscribe(Tenant $tenant, Plan $plan): Subscription
    {
        return Subscription::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ]);
    }
}

beforeEach(function () {
    Entitlements::flush();
});

it('resolves a boolean feature from the active plan', function () {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([Feature::Bookings->value => true]));

    expect(Entitlements::for($tenant)->enabled(Feature::Bookings))->toBeTrue();
});

it('lets a live override beat the plan value', function () {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([Feature::Bookings->value => false]));

    TenantFeatureOverride::factory()->forFeature(Feature::Bookings, true)->create(['tenant_id' => $tenant->id]);

    expect(Entitlements::for($tenant)->enabled(Feature::Bookings))->toBeTrue();
});

it('ignores an expired override and falls back to the plan', function () {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([Feature::Bookings->value => true]));

    TenantFeatureOverride::factory()->forFeature(Feature::Bookings, false)->expired()->create(['tenant_id' => $tenant->id]);

    expect(Entitlements::for($tenant)->enabled(Feature::Bookings))->toBeTrue();
});

it('resolves every falsy encoding of a boolean feature to off', function (mixed $stored) {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([Feature::Bookings->value => $stored]));

    expect(Entitlements::for($tenant)->enabled(Feature::Bookings))->toBeFalse();
})->with([
    'int zero' => [0],
    'string zero' => ['0'],
    'false' => [false],
    'null' => [null],
    'empty string' => [''],
]);

it('resolves truthy encodings of a boolean feature to on', function (mixed $stored) {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([Feature::Bookings->value => $stored]));

    expect(Entitlements::for($tenant)->enabled(Feature::Bookings))->toBeTrue();
})->with([
    'true' => [true],
    'int one' => [1],
    'string one' => ['1'],
]);

it('falls back to the default plan when the tenant has no subscription', function () {
    entPlanWith([Feature::Bookings->value => true], default: true);
    $tenant = Tenant::factory()->create();

    expect(Entitlements::for($tenant)->enabled(Feature::Bookings))->toBeTrue();
});

it('uses the feature default when neither override nor plan specify it', function () {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([])); // empty plan

    expect(Entitlements::for($tenant)->enabled(Feature::Bookings))->toBeFalse();
    expect(Entitlements::for($tenant)->limit(Feature::MaxProducts))->toBe(0);
});

it('resolves numeric limits and treats null as unlimited', function () {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([
        Feature::MaxProducts->value => 50,
        Feature::MaxUsers->value => null,
    ]));

    expect(Entitlements::for($tenant)->limit(Feature::MaxProducts))->toBe(50);
    expect(Entitlements::for($tenant)->limit(Feature::MaxUsers))->toBeNull();
});

it('computes remaining usage against the cap', function () {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([Feature::MaxUsers->value => 5]));

    // Attach three users to the tenant.
    User::factory()->count(3)->create()->each(
        fn ($user) => $tenant->users()->attach($user, ['role' => 'staff', 'is_active' => true])
    );

    expect(Entitlements::for($tenant)->usage(Feature::MaxUsers))->toBe(3);
    expect(Entitlements::for($tenant)->remaining(Feature::MaxUsers))->toBe(2);
    expect(Entitlements::for($tenant)->allows(Feature::MaxUsers, 2))->toBeTrue();
    expect(Entitlements::for($tenant)->allows(Feature::MaxUsers, 3))->toBeFalse();
});

it('throws when asking a limit feature whether it is enabled', function () {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([]));

    Entitlements::for($tenant)->enabled(Feature::MaxProducts);
})->throws(InvalidArgumentException::class);

it('throws when asking a boolean feature for its limit', function () {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([]));

    Entitlements::for($tenant)->limit(Feature::Bookings);
})->throws(InvalidArgumentException::class);

it('resolves the ambient tenant like the tenant scope', function () {
    $tenant = app('currentTenant'); // bound by the Feature beforeEach
    entSubscribe($tenant, entPlanWith([Feature::Bookings->value => true]));

    expect(Entitlements::enabled(Feature::Bookings))->toBeTrue();
});

it('throws when no tenant context is resolvable', function () {
    app()->forgetInstance('currentTenant');

    Entitlements::enabled(Feature::Bookings);
})->throws(NoTenantContextException::class);

it('keeps separate tenants isolated', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    entSubscribe($a, entPlanWith([Feature::Bookings->value => true]));
    entSubscribe($b, entPlanWith([Feature::Bookings->value => false]));

    expect(Entitlements::for($a)->enabled(Feature::Bookings))->toBeTrue();
    expect(Entitlements::for($b)->enabled(Feature::Bookings))->toBeFalse();
});

it('loads each tenant once and serves further reads from cache', function () {
    $tenant = Tenant::factory()->create();
    entSubscribe($tenant, entPlanWith([Feature::Bookings->value => true, Feature::MaxProducts->value => 10]));
    Entitlements::flush();

    DB::connection()->enableQueryLog();
    $resolved = Entitlements::for($tenant);
    foreach (Feature::booleans() as $feature) {
        $resolved->enabled($feature);
    }
    foreach (Feature::limits() as $feature) {
        $resolved->limit($feature);
    }
    $buildQueries = DB::connection()->getQueryLog();

    expect(count($buildQueries))->toBeLessThanOrEqual(5);

    DB::connection()->flushQueryLog();
    Entitlements::for($tenant)->enabled(Feature::Bookings);
    expect(DB::connection()->getQueryLog())->toBeEmpty();
});

it('re-reads after flush', function () {
    $tenant = Tenant::factory()->create();
    $plan = entPlanWith([Feature::Bookings->value => false]);
    entSubscribe($tenant, $plan);

    expect(Entitlements::for($tenant)->enabled(Feature::Bookings))->toBeFalse();

    $plan->planFeatures()->where('feature_key', Feature::Bookings->value)->update(['value' => json_encode(true)]);
    Entitlements::flush($tenant);

    expect(Entitlements::for($tenant)->enabled(Feature::Bookings))->toBeTrue();
});
