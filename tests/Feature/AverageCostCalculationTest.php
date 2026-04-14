<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Queries\DashboardStatsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calculates average cost correctly after multiple purchases', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['cost' => 10]); // Initial default cost

    // Purchase 1: 10 units @ $10
    $purchase1 = Invoice::create([
        'invocable_id' => Supplier::factory()->create()->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-001',
        'total' => 100,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchase1->id,
        'quantity' => 10,
        'base_quantity' => 10,
        'price' => 10,
        'unit_cost' => 10,
        'delivered' => true,
    ]);

    // Purchase 2: 20 units @ $25
    $purchase2 = Invoice::create([
        'invocable_id' => Supplier::factory()->create()->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-002',
        'total' => 500,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchase2->id,
        'quantity' => 20,
        'base_quantity' => 20,
        'price' => 25,
        'unit_cost' => 25,
        'delivered' => true,
    ]);

    // Total Cost = (10 * 10) + (20 * 25) = 100 + 500 = 600
    // Total Qty = 10 + 20 = 30
    // Avg Cost = 600 / 30 = 20

    $product->refresh();
    expect((float) $product->average_cost)->toBe(20.0);
});

test('pending sales and purchases are tracked correctly', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['alert_quantity' => 5]);
    $product->stock()->attach($storage->id, ['quantity' => 10]);

    // Pending Purchase: 5 units
    $purchase = Invoice::create([
        'invocable_id' => Supplier::factory()->create()->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-PND',
        'total' => 50,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchase->id,
        'quantity' => 5,
        'base_quantity' => 5,
        'price' => 10,
        'delivered' => false,
    ]);

    // Pending Sale: 3 units
    $sale = Invoice::create([
        'invocable_id' => Customer::factory()->create()->id,
        'invocable_type' => Customer::class,
        'serial_number' => 'SAL-PND',
        'total' => 60,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $sale->id,
        'quantity' => 3,
        'base_quantity' => 3,
        'price' => 20,
        'delivered' => false,
    ]);

    $product->refresh();
    expect((float) $product->pending_purchases)->toBe(5.0);
    expect((float) $product->pending_sales)->toBe(3.0);
    expect($product->quantityOnHand())->toBe(10);
    expect((float) $product->available_qty)->toBe(7.0); // 10 - 3
    expect((float) $product->expectedQuantity())->toBe(15.0); // 10 + 5
});

test('inventory value uses average cost', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['cost' => 50]);
    $product->stock()->attach($storage->id, ['quantity' => 10]);

    // Purchase @ $20 (avg cost will be 20 if it's the only delivered purchase)
    $purchase = Invoice::create([
        'invocable_id' => Supplier::factory()->create()->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-001',
        'total' => 100,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchase->id,
        'quantity' => 5,
        'base_quantity' => 5,
        'price' => 20,
        'unit_cost' => 20,
        'delivered' => true,
    ]);

    $query = new DashboardStatsQuery;
    // 10 units * $20 avg cost = $200
    expect($query->totalInventoryValue())->toBe(200.0);
});
