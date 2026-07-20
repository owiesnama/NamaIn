<?php

use App\Features\Feature;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;

it('seeds the three starter tiers with one default', function () {
    (new PlanSeeder)->run();

    expect(Plan::count())->toBe(3);
    expect(Plan::where('is_default', true)->count())->toBe(1);
    expect(Plan::where('key', 'free')->value('is_default'))->toBeTrue();
});

it('only assigns feature keys that exist in the catalog', function () {
    (new PlanSeeder)->run();

    $validKeys = array_map(fn (Feature $f) => $f->value, Feature::cases());

    Plan::with('planFeatures')->get()->each(function (Plan $plan) use ($validKeys) {
        $plan->planFeatures->each(
            fn ($planFeature) => expect($planFeature->feature_key)->toBeIn($validKeys)
        );
    });
});

it('is idempotent', function () {
    (new PlanSeeder)->run();
    (new PlanSeeder)->run();

    expect(Plan::count())->toBe(3);
});
