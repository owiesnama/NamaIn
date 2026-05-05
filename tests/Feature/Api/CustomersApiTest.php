<?php

use App\Models\Customer;

test('it returns paginated customers', function () {
    actingAsTenantUser();
    Customer::factory()->count(25)->create();

    $response = $this->getJson('/api/customers');

    $response->assertOk();
    $response->assertJsonCount(20, 'data');
    $response->assertJsonStructure([
        'data' => [['id', 'name']],
        'next_page_url',
    ]);
});

test('it searches customers by name', function () {
    actingAsTenantUser();
    Customer::factory()->create(['name' => 'John Doe']);
    Customer::factory()->create(['name' => 'Jane Smith']);

    $response = $this->getJson('/api/customers?search=John');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toBe('John Doe');
});

test('it excludes system customers', function () {
    actingAsTenantUser();
    Customer::factory()->create(['name' => 'Regular', 'is_system' => false]);
    Customer::factory()->create(['name' => 'System', 'is_system' => true]);

    $response = $this->getJson('/api/customers');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toBe('Regular');
});

test('it requires authentication', function () {
    $response = $this->getJson('/api/customers');

    $response->assertUnauthorized();
});
