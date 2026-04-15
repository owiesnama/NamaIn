<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->user = User::factory()->create(['role' => 'user']);
    $this->storage = Storage::factory()->create(['name' => 'Main Warehouse']);
    $this->product = Product::factory()->create();
});

test('unauthorized users cannot manage stock', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Initial]);

    $this->actingAs($this->user)
        ->put(route('stock.add', $this->storage), ['invoice' => $invoice->id])
        ->assertForbidden();

    $this->actingAs($this->user)
        ->put(route('stock.deduct', $this->storage), ['invoice' => $invoice->id])
        ->assertForbidden();
});

test('authorized users can add stock from invoice', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Initial]);
    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $this->product->id,
        'storage_id' => $this->storage->id,
        'base_quantity' => 10,
        'delivered' => false,
    ]);

    $this->actingAs($this->admin)
        ->put(route('stock.add', $this->storage), ['invoice' => $invoice->id])
        ->assertRedirect();

    expect($this->storage->fresh()->quantityOf($this->product))->toBe(10);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Delivered);
});

test('authorized users can deduct stock from invoice', function () {
    // Setup initial stock
    $this->storage->addStock(['product' => $this->product->id, 'quantity' => 50]);

    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Initial]);
    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $this->product->id,
        'storage_id' => $this->storage->id,
        'base_quantity' => 10,
        'delivered' => false,
    ]);

    $this->actingAs($this->admin)
        ->put(route('stock.deduct', $this->storage), ['invoice' => $invoice->id])
        ->assertRedirect();

    expect($this->storage->fresh()->quantityOf($this->product))->toBe(40);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Delivered);
});

test('deducting stock handles partial delivery when stock is insufficient', function () {
    // Setup initial stock (less than needed)
    $this->storage->addStock(['product' => $this->product->id, 'quantity' => 5]);

    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Initial]);
    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $this->product->id,
        'storage_id' => $this->storage->id,
        'base_quantity' => 10,
        'delivered' => false,
    ]);

    $this->actingAs($this->admin)
        ->put(route('stock.deduct', $this->storage), ['invoice' => $invoice->id])
        ->assertRedirect();

    // Storage should be empty now
    expect($this->storage->fresh()->quantityOf($this->product))->toBe(0);

    // Original transaction should be updated to 5 and marked delivered
    $deliveredTransaction = Transaction::where('invoice_id', $invoice->id)->where('delivered', true)->first();
    expect($deliveredTransaction->base_quantity)->toBe(5);

    // New transaction should be created for the remaining 5
    $pendingTransaction = Transaction::where('invoice_id', $invoice->id)->where('delivered', false)->first();
    expect($pendingTransaction->base_quantity)->toBe(5);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyDelivered);
});

test('cannot process already delivered invoice', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Delivered]);

    $this->actingAs($this->admin)
        ->put(route('stock.add', $this->storage), ['invoice' => $invoice->id])
        ->assertSessionHasErrors('invoice');
});

test('stock cache is invalidated after stock operations', function () {
    Cache::put('low_stock_products', ['some' => 'data']);

    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Initial]);
    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $this->product->id,
        'storage_id' => $this->storage->id,
        'base_quantity' => 10,
        'delivered' => false,
    ]);

    $this->actingAs($this->admin)
        ->put(route('stock.add', $this->storage), ['invoice' => $invoice->id]);

    expect(Cache::has('low_stock_products'))->toBeFalse();
});
