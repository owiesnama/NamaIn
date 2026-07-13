<?php

use App\Models\Product;
use App\Models\ServiceAddon;

test('an add-on belongs to its service and round-trips its money delta', function () {
    $service = Product::factory()->service()->create();
    $addon = ServiceAddon::factory()->create([
        'product_id' => $service->id,
        'price_delta' => 75,
    ]);

    expect($addon->refresh()->price_delta)->toBe(75.0)
        ->and($addon->product->is($service))->toBeTrue();
});

test('a zero price delta is a valid (free) add-on', function () {
    $addon = ServiceAddon::factory()->create(['price_delta' => 0]);

    expect($addon->refresh()->price_delta)->toBe(0.0);
});

test('add-ons cascade when their product is deleted', function () {
    $service = Product::factory()->service()->create();
    $addon = ServiceAddon::factory()->create(['product_id' => $service->id]);

    $service->forceDelete();

    expect(ServiceAddon::withTrashed()->whereKey($addon->id)->exists())->toBeFalse();
});

test('add-ons are tenant scoped', function () {
    $addon = ServiceAddon::factory()->create();

    expect($addon->tenant_id)->not->toBeNull();
});
