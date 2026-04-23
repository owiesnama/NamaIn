<?php

use App\Actions\Purchase\ReceiveGoodsAction;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
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
    $this->supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('it can receive goods for a purchase transaction', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'invocable_type' => Supplier::class,
        'invocable_id' => $this->supplier->id,
    ]);
    $transaction = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'product_id' => $this->product->id,
        'storage_id' => $this->storage->id,
        'base_quantity' => 100,
        'delivered' => false,
    ]);

    app(ReceiveGoodsAction::class)->execute($transaction, $this->storage, 40, $this->owner);

    expect($transaction->fresh()->received_quantity)->toBe(40);
    expect($transaction->fresh()->remaining_quantity)->toBe(60);
    expect($this->storage->fresh()->quantityOf($this->product))->toBe(40);
    expect($transaction->fresh()->delivered)->toBeFalse();
});

test('it can fully receive a purchase transaction', function () {
    $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
    $transaction = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'product_id' => $this->product->id,
        'storage_id' => $this->storage->id,
        'base_quantity' => 100,
        'delivered' => false,
    ]);

    app(ReceiveGoodsAction::class)->execute($transaction, $this->storage, 100, $this->owner);

    expect($transaction->fresh()->received_quantity)->toBe(100);
    expect($transaction->fresh()->isFullyReceived())->toBeTrue();
    expect($transaction->fresh()->delivered)->toBeTrue();
});

test('it rejects over-receiving', function () {
    $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
    $transaction = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'product_id' => $this->product->id,
        'storage_id' => $this->storage->id,
        'base_quantity' => 100,
    ]);

    expect(fn () => app(ReceiveGoodsAction::class)->execute($transaction, $this->storage, 110, $this->owner))
        ->toThrow(DomainException::class);
});
