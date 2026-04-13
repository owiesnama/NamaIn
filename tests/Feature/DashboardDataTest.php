<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard displays enriched data', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['name' => 'Test Product']);
    $customer = Customer::factory()->create(['name' => 'Test Customer']);

    // Create a sale to show up in top products
    $invoice = Invoice::create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 1000,
        'serial_number' => 'INV-001',
        'status' => 'delivered',
    ]);

    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $invoice->id,
        'quantity' => 10,
        'base_quantity' => 10,
        'price' => 100,
        'delivered' => 1,
        'created_at' => now(),
    ]);

    // Create a low stock product
    $lowStockProduct = Product::factory()->create(['name' => 'Low Stock Item']);
    $lowStockProduct->stock()->attach($storage->id, ['quantity' => 1]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertStatus(200);
    $data = $response->viewData('page')['props'];

    $this->assertCount(1, $data['top_products']);
    $this->assertEquals('Test Product', $data['top_products'][0]['product']['name']);

    $lowStockNames = collect($data['low_stock_products'])->pluck('name');
    $this->assertTrue($lowStockNames->contains('Low Stock Item'));

    // Check transactions
    $this->assertNotEmpty($data['transactions']);
    $this->assertNotEmpty($data['transactions'][0]['created_at']);
    // Test that the created_at accessor works and returns a string (human-readable)
    $this->assertIsString($data['transactions'][0]['created_at']);
});
