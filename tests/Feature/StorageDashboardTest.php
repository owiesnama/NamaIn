<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('storage dashboard displays correctly', function () {
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $storage = Storage::factory()->create(['name' => 'Main Warehouse']);
    $product = Product::factory()->create(['name' => 'Widget', 'cost' => 100]);

    // Add stock to storage
    $storage->stock()->attach($product->id, ['quantity' => 10]);

    // Create a Purchase transaction for this storage
    $supplier = Supplier::factory()->create();
    $purchaseInvoice = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
    ]);
    Transaction::factory()->create([
        'product_id' => $product->id,
        'invoice_id' => $purchaseInvoice->id,
        'storage_id' => $storage->id,
        'quantity' => 10,
        'base_quantity' => 10,
        'price' => 100,
    ]);

    // Create a Sale transaction for this storage
    $customer = Customer::factory()->create();
    $saleInvoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
    ]);
    Transaction::factory()->create([
        'product_id' => $product->id,
        'invoice_id' => $saleInvoice->id,
        'storage_id' => $storage->id,
        'quantity' => 3,
        'base_quantity' => 3,
        'price' => 150,
    ]);

    $response = $this->actingAs($user)->get(route('storages.show', $storage));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Storages/Show')
            ->has('storage')
            ->has('products.data', 1)
            ->has('transactions.data', 2)
            ->where('stats.sales_count', 3)
            ->where('stats.purchases_count', 10)
            ->where('stats.total_stock_value', 1000) // 10 * 100
            ->where('stats.unique_products_count', 1)
            ->has('all_products', 1)
            ->has('chart_data')
            ->has('filters')
        );
});

test('storage dashboard filtering works', function () {
    $user = User::factory()->create();
    $user->markEmailAsVerified();
    $storage = Storage::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $supplier = Supplier::factory()->create();

    // Transaction for product 1
    $purchaseInvoice1 = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
    ]);
    Transaction::factory()->create([
        'product_id' => $product1->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchaseInvoice1->id,
        'quantity' => 10,
        'base_quantity' => 10,
    ]);

    // Transaction for product 2
    $purchaseInvoice2 = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
    ]);
    Transaction::factory()->create([
        'product_id' => $product2->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchaseInvoice2->id,
        'quantity' => 5,
        'base_quantity' => 5,
    ]);

    // Filter by product 1
    $response = $this->actingAs($user)->get(route('storages.show', [
        'storage' => $storage,
        'product_id' => $product1->id,
    ]));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('stats.purchases_count', 10)
        );

    // Filter by type Sales (should be 0)
    $response = $this->actingAs($user)->get(route('storages.show', [
        'storage' => $storage,
        'type' => 'Sales',
    ]));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 0)
            ->where('stats.purchases_count', 0)
        );
});
