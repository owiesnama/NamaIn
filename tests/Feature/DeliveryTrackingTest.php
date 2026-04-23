<?php

use App\Actions\Stock\DeliverTransactionAction;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $this->tenant);

    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner']);

    $this->storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->storage->addStock($this->product, 100, 'initial_stock', actor: $this->owner);
});

test('it can deliver a transaction and track it', function () {
    $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
    $transaction = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'product_id' => $this->product->id,
        'storage_id' => $this->storage->id,
        'base_quantity' => 10,
        'delivered' => false,
    ]);

    app(DeliverTransactionAction::class)->execute($transaction, $this->owner);

    $transaction->refresh();
    expect($transaction->delivered)->toBeTrue();
    expect($transaction->delivered_at)->not->toBeNull();
    expect($transaction->delivered_by)->toBe($this->owner->id);
    expect($transaction->fulfilled_from_storage_id)->toBe($this->storage->id);
    expect($this->storage->fresh()->quantityOf($this->product))->toBe(90);

    // Verify ledger entry
    $this->assertDatabaseHas('stock_movements', [
        'storage_id' => $this->storage->id,
        'product_id' => $this->product->id,
        'quantity' => -10,
        'reason' => 'sale_delivery',
    ]);
});

test('it can deliver from a different storage than assigned', function () {
    $altStorage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $altStorage->addStock($this->product, 50, 'initial_stock', actor: $this->owner);

    $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
    $transaction = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'product_id' => $this->product->id,
        'storage_id' => $this->storage->id, // Originally assigned to main storage
        'base_quantity' => 10,
        'delivered' => false,
    ]);

    app(DeliverTransactionAction::class)->execute($transaction, $this->owner, $altStorage);

    $transaction->refresh();
    expect($transaction->fulfilled_from_storage_id)->toBe($altStorage->id);
    expect($altStorage->fresh()->quantityOf($this->product))->toBe(40);
    expect($this->storage->fresh()->quantityOf($this->product))->toBe(100); // Main storage untouched
});
