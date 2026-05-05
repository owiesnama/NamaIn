<?php

use App\Models\Product;

test('it returns paginated products', function () {
    actingAsTenantUser();
    Product::factory()->count(25)->create();

    $response = $this->getJson('/api/products');

    $response->assertOk();
    $response->assertJsonCount(20, 'data');
    $response->assertJsonStructure([
        'data' => [['id', 'name']],
        'next_page_url',
    ]);
});

test('it searches products by name', function () {
    actingAsTenantUser();
    Product::factory()->create(['name' => 'Widget Alpha']);
    Product::factory()->create(['name' => 'Gadget Beta']);

    $response = $this->getJson('/api/products?search=Widget');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toBe('Widget Alpha');
});

test('it includes units relation', function () {
    actingAsTenantUser();
    $product = Product::factory()->create();
    $product->units()->create(['name' => 'Box', 'conversion_factor' => 1]);

    $response = $this->getJson('/api/products');

    $response->assertOk();
    $response->assertJsonStructure(['data' => [['id', 'name', 'units']]]);
    expect($response->json('data.0.units'))->toHaveCount(1);
});

test('it paginates to next page', function () {
    actingAsTenantUser();
    Product::factory()->count(25)->create();

    $response = $this->getJson('/api/products?page=2');

    $response->assertOk();
    $response->assertJsonCount(5, 'data');
});

test('it requires authentication', function () {
    $response = $this->getJson('/api/products');

    $response->assertUnauthorized();
});
