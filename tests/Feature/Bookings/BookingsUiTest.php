<?php

use App\Models\Booking;
use App\Models\Product;
use App\Models\ServiceAddon;

test('the bookings index renders with its bookings', function () {
    $this->signIn();
    $service = Product::factory()->service()->create(['duration_minutes' => 60]);
    Booking::factory()->count(2)->create(['service_product_id' => $service->id]);

    $this->get(route('bookings.index'))
        ->assertInertia(fn ($page) => $page->component('Bookings/Index')->has('bookings.data', 2));
});

test('the products index filters by type', function () {
    $this->signIn();
    Product::factory()->count(2)->create();
    Product::factory()->service()->count(3)->create();

    $this->get(route('products.index', ['type' => 'service']))
        ->assertInertia(fn ($page) => $page->component('Products/Index')->has('products.data', 3));
});

test('the products API eager-loads add-ons for services', function () {
    $this->signIn();
    $service = Product::factory()->service()->create();
    ServiceAddon::factory()->create(['product_id' => $service->id]);

    $response = $this->getJson(route('api.products.index', ['type' => 'service']));

    $response->assertOk();
    expect($response->json('data.0.service_addons'))->toHaveCount(1);
});
