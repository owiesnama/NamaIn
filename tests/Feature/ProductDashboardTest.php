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

test('product dashboard displays correctly', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Widget']);
    $storage = Storage::factory()->create();

    // Create a Purchase
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
        'price' => 50,
    ]);

    // Create a Sale
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
        'price' => 80,
    ]);

    $response = $this->actingAs($user)->get(route('products.show', $product));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Products/Show')
            ->has('product')
            ->has('transactions.data', 2)
            ->where('stats.sales_count', 3)
            ->where('stats.purchases_count', 10)
            ->has('storages')
            ->has('chart_data')
            ->has('filters')
        );
});

test('product dashboard filtering works', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $storage1 = Storage::factory()->create();
    $storage2 = Storage::factory()->create();
    $supplier = Supplier::factory()->create();

    // Transaction in storage 1 (Purchase)
    $purchaseInvoice = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
    ]);
    Transaction::factory()->create([
        'product_id' => $product->id,
        'storage_id' => $storage1->id,
        'invoice_id' => $purchaseInvoice->id,
        'quantity' => 10,
    ]);

    // Transaction in storage 2 (Purchase)
    $purchaseInvoice2 = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
    ]);
    Transaction::factory()->create([
        'product_id' => $product->id,
        'storage_id' => $storage2->id,
        'invoice_id' => $purchaseInvoice2->id,
        'quantity' => 5,
    ]);

    // Filter by storage 1
    $response = $this->actingAs($user)->get(route('products.show', [
        'product' => $product,
        'storage_id' => $storage1->id,
    ]));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('stats.purchases_count', 10)
        );

    // Filter by type Sales (should be 0)
    $response = $this->actingAs($user)->get(route('products.show', [
        'product' => $product,
        'type' => 'Sales',
    ]));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 0)
            ->where('stats.purchases_count', 0)
        );
});
