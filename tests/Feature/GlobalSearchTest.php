<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can search for products globally', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Super Widget']);

    $response = $this->actingAs($user)->get(route('global-search', ['search' => 'Super']));

    $response->assertStatus(200);
    expect($response->json())->toHaveCount(1);
    expect($response->json()[0]['name'])->toBe('Super Widget');
    expect($response->json()[0]['type'])->toBe('Product');
});

test('can search for customers globally', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['name' => 'John Doe', 'phone_number' => '123456']);

    $response = $this->actingAs($user)->get(route('global-search', ['search' => 'John']));

    expect($response->json())->toHaveCount(1);
    expect($response->json()[0]['name'])->toBe('John Doe');
    expect($response->json()[0]['type'])->toBe('Customer');
});

test('can search for suppliers globally', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['name' => 'Jane Smith', 'phone_number' => '654321']);

    $response = $this->actingAs($user)->get(route('global-search', ['search' => 'Jane']));

    expect($response->json())->toHaveCount(1);
    expect($response->json()[0]['name'])->toBe('Jane Smith');
    expect($response->json()[0]['type'])->toBe('Supplier');
});

test('returns empty results when no search term provided', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('global-search'));

    $response->assertStatus(200);
    expect($response->json())->toHaveCount(0);
});
