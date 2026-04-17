<?php

use App\Models\Category;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can search by direct attributes', function () {
    Product::factory()->create(['name' => 'iPhone 15']);
    Product::factory()->create(['name' => 'Samsung S24']);

    $results = Product::search('iPhone')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('iPhone 15');
});

test('it can search by relation attributes', function () {
    $category = Category::factory()->create(['name' => 'Electronics']);
    $product = Product::factory()->create(['name' => 'iPhone 15']);
    $product->categories()->attach($category);

    Product::factory()->create(['name' => 'Table']); // No category

    $results = Product::search('Electronics')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('iPhone 15');
});

test('it is case insensitive', function () {
    Product::factory()->create(['name' => 'iPhone 15']);

    $results = Product::search('iphone')->get();

    expect($results)->toHaveCount(1);
});

test('it handles nested relations', function () {
    $customer = Customer::factory()->create(['name' => 'John Doe']);
    $invoice = Invoice::factory()->create(['invocable_id' => $customer->id, 'invocable_type' => Customer::class]);
    $cheque = Cheque::factory()->create([
        'invoice_id' => $invoice->id,
        'chequeable_id' => $customer->id,
        'chequeable_type' => Customer::class,
    ]);

    // Cheque has 'payee' which is morphTo (Customer).
    // Let's add a nested search to Cheque for testing purposes in this test if possible,
    // but we can just use Invoice which has 'invocable.name'

    $results = Invoice::search('John Doe')->get();
    expect($results)->toHaveCount(1);
});

test('it handles deeply nested relations', function () {
    $category = Category::factory()->create(['name' => 'Tech']);
    $product = Product::factory()->create(['name' => 'MacBook']);
    $product->categories()->attach($category);
    $transaction = Transaction::factory()->create(['product_id' => $product->id]);

    // Transaction search might need to be configured. Let's check Transaction model.
    // Assuming Transaction has searchableRelationsAttributes = ['product.categories.name']
    // If not, we'll see it fail or we can add it for the test.

    $results = Transaction::search('Tech')->get();
    expect($results)->toHaveCount(1);
});

test('it handles empty search term', function () {
    Product::factory()->create(['name' => 'iPhone 15']);

    $results = Product::search('')->get();

    expect($results)->toHaveCount(1);
});
