<?php

use App\Features\Feature;
use App\Features\FeatureType;

it('assigns every case a type, group, label key and default', function () {
    foreach (Feature::cases() as $feature) {
        expect($feature->type())->toBeInstanceOf(FeatureType::class);
        expect($feature->group())->toBeString()->not->toBeEmpty();
        expect($feature->labelKey())->toBe('features.'.$feature->value);
        expect($feature->default())->toBeIn([false, 0, null]);
    }
});

it('defaults booleans to off and limits to zero', function () {
    foreach (Feature::booleans() as $feature) {
        expect($feature->default())->toBeFalse();
    }

    foreach (Feature::limits() as $feature) {
        expect($feature->default())->toBe(0);
    }
});

it('partitions cases into booleans and limits with no overlap', function () {
    $booleans = Feature::booleans();
    $limits = Feature::limits();

    expect(count($booleans) + count($limits))->toBe(count(Feature::cases()));

    foreach ($booleans as $feature) {
        expect($feature->isBoolean())->toBeTrue();
        expect($limits)->not->toContain($feature);
    }

    foreach ($limits as $feature) {
        expect($feature->isLimit())->toBeTrue();
    }
});

it('has unique backing values', function () {
    $values = array_map(fn (Feature $f) => $f->value, Feature::cases());

    expect($values)->toEqual(array_unique($values));
});
