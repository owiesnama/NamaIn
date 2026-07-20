<?php

use App\Models\Product;

test('sellableAsLineItem excludes bookable services but keeps physical and walk-in services', function () {
    $physical = Product::factory()->create();
    $walkIn = Product::factory()->service()->create(['requires_booking' => false]);
    $bookable = Product::factory()->service()->create(['requires_booking' => true]);

    $ids = Product::sellableAsLineItem()->pluck('id');

    expect($ids)->toContain($physical->id)
        ->toContain($walkIn->id)
        ->not->toContain($bookable->id);
});

test('the products API line_sale filter hides bookable services', function () {
    $this->signIn();
    Product::factory()->create();                                        // physical
    Product::factory()->service()->create(['requires_booking' => false]); // walk-in
    Product::factory()->service()->create(['requires_booking' => true]);  // bookable

    $response = $this->getJson(route('api.products.index', ['line_sale' => 1]));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2); // physical + walk-in, not the bookable
});
