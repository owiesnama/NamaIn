<?php

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ServiceAddon;

test('a plain product defaults to the physical type (backfill/default meaning)', function () {
    $product = Product::factory()->create();

    expect($product->refresh()->type)->toBe(ProductType::Physical)
        ->and($product->isService())->toBeFalse()
        ->and($product->requires_booking)->toBeFalse()
        ->and($product->on_site)->toBeFalse()
        ->and($product->allow_overlap)->toBeFalse()
        ->and($product->duration_minutes)->toBeNull()
        ->and($product->travel_buffer_minutes)->toBeNull();
});

test('the service factory state produces a bookable service', function () {
    $service = Product::factory()->service()->create();

    expect($service->refresh()->type)->toBe(ProductType::Service)
        ->and($service->isService())->toBeTrue()
        ->and($service->requires_booking)->toBeTrue()
        ->and($service->duration_minutes)->toBeGreaterThan(0);
});

test('the on-site service state sets a travel buffer', function () {
    $service = Product::factory()->service()->onSite(45)->create();

    expect($service->refresh()->on_site)->toBeTrue()
        ->and($service->travel_buffer_minutes)->toBe(45);
});

test('services and physical scopes partition the catalog', function () {
    Product::factory()->count(2)->create();
    Product::factory()->service()->count(3)->create();

    expect(Product::physical()->count())->toBe(2)
        ->and(Product::services()->count())->toBe(3);
});

test('a service has many add-ons', function () {
    $service = Product::factory()->service()->create();
    ServiceAddon::factory()->count(2)->create(['product_id' => $service->id]);

    expect($service->serviceAddons)->toHaveCount(2)
        ->and($service->serviceAddons->first())->toBeInstanceOf(ServiceAddon::class);
});
