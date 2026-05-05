<?php

use App\Models\Supplier;

test('it returns paginated suppliers', function () {
    actingAsTenantUser();
    Supplier::factory()->count(25)->create();

    $response = $this->getJson('/api/suppliers');

    $response->assertOk();
    $response->assertJsonCount(20, 'data');
    $response->assertJsonStructure([
        'data' => [['id', 'name']],
        'next_page_url',
    ]);
});

test('it searches suppliers by name', function () {
    actingAsTenantUser();
    Supplier::factory()->create(['name' => 'Acme Corp']);
    Supplier::factory()->create(['name' => 'Beta Inc']);

    $response = $this->getJson('/api/suppliers?search=Acme');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toBe('Acme Corp');
});

test('it requires authentication', function () {
    $response = $this->getJson('/api/suppliers');

    $response->assertUnauthorized();
});
