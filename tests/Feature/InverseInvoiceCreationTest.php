<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it can create a return for a sale invoice', function () {
    $customer = Customer::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $unit = Unit::factory()->create(['product_id' => $product->id, 'conversion_factor' => 1]);

    // Create a sale invoice
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 100,
        'currency' => 'SDG',
        'status' => InvoiceStatus::Initial,
    ]);

    $transaction = Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'quantity' => 10,
        'base_quantity' => 10,
        'price' => 10,
        'unit_id' => $unit->id,
    ]);

    $response = $this->post(route('sales.return.store', $invoice), [
        'parent_invoice_id' => $invoice->id,
        'inverse_reason' => 'Defective products',
        'products' => [
            [
                'transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'quantity' => 5,
                'unit_id' => $unit->id,
                'price' => 10,
            ],
        ],
        'total' => 50,
        'refund_amount' => 50,
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect(route('sales.index'));
    $this->assertDatabaseHas('invoices', [
        'parent_invoice_id' => $invoice->id,
        'is_inverse' => true,
        'total' => 50,
        'inverse_reason' => 'Defective products',
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Returned);

    // Verify stock movement (Sale return should add stock back)
    // Assuming product->quantityOnHand() uses the stocks table
    $this->assertDatabaseHas('stocks', [
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'quantity' => 5, // 5 added back
    ]);
});

test('it can create a return for a purchase invoice', function () {
    $supplier = Supplier::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $unit = Unit::factory()->create(['product_id' => $product->id, 'conversion_factor' => 1]);

    // Initial stock (Purchase return needs existing stock to deduct)
    $storage->addStock(
        product: $product->id,
        quantity: 20,
        reason: 'test_addition'
    );

    // Create a purchase invoice
    $invoice = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
        'total' => 100,
        'currency' => 'SDG',
        'status' => InvoiceStatus::Initial,
    ]);

    $transaction = Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'quantity' => 10,
        'base_quantity' => 10,
        'price' => 10,
        'unit_id' => $unit->id,
    ]);

    $response = $this->post(route('purchases.return.store', $invoice), [
        'parent_invoice_id' => $invoice->id,
        'inverse_reason' => 'Wrong items received',
        'products' => [
            [
                'transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_id' => $unit->id,
                'price' => 10,
            ],
        ],
        'total' => 100,
        'refund_amount' => 100,
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect(route('purchases.index'));
    $this->assertDatabaseHas('invoices', [
        'parent_invoice_id' => $invoice->id,
        'is_inverse' => true,
        'total' => 100,
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Returned);

    // Verify stock movement (Purchase return should deduct stock)
    $this->assertDatabaseHas('stocks', [
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'quantity' => 10, // 20 - 10 = 10
    ]);
});
