<?php

use App\Features\Feature;
use App\Features\LimitUsage;

it('registers a usage mapping for every limit feature', function () {
    $usage = new LimitUsage;

    foreach (Feature::limits() as $feature) {
        expect($usage->has($feature))->toBeTrue("Missing LimitUsage mapping for [{$feature->value}].");
    }
});

it('has no usage mapping for boolean features', function () {
    $usage = new LimitUsage;

    foreach (Feature::booleans() as $feature) {
        expect($usage->has($feature))->toBeFalse();
    }
});
