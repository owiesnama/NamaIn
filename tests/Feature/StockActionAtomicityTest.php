<?php

use App\Actions\Stock\AddStockFromInvoice;
use App\Actions\Stock\DeductStockFromInvoice;
use App\Enums\InvoiceStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Role;
use App\Models\Storage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = app('currentTenant');
    seedTenantRoles($this->tenant);

    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    $this->storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->stockedProduct = Product::factory()->create();
    $this->otherProduct = Product::factory()->create();

    $this->invoice = Invoice::factory()->create(['status' => InvoiceStatus::Initial]);

    $this->stockedLine = Transaction::factory()->create([
        'invoice_id' => $this->invoice->id,
        'product_id' => $this->stockedProduct->id,
        'storage_id' => $this->storage->id,
        'quantity' => 5,
        'base_quantity' => 5,
        'price' => 100,
        'delivered' => false,
    ]);

    $this->failingLine = Transaction::factory()->create([
        'invoice_id' => $this->invoice->id,
        'product_id' => $this->otherProduct->id,
        'storage_id' => $this->storage->id,
        'quantity' => 5,
        'base_quantity' => 5,
        'price' => 100,
        'delivered' => false,
    ]);
});

test('deducting an invoice rolls back every line when one line has no stock at all', function () {
    $this->storage->addStock($this->stockedProduct, 10, 'initial_stock', actor: $this->owner);
    // otherProduct has no stock row anywhere, so its line throws mid-loop.

    expect(fn () => app(DeductStockFromInvoice::class)->handle($this->invoice, $this->storage, $this->owner))
        ->toThrow(InsufficientStockException::class);

    expect($this->storage->fresh()->quantityOf($this->stockedProduct))->toBe(10)
        ->and($this->stockedLine->fresh()->delivered)->toBeFalse()
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Initial)
        ->and($this->invoice->transactions()->count())->toBe(2);
});

test('adding an invoice rolls back every line when a later line fails', function () {
    $this->otherProduct->delete(); // deleted between invoicing and receiving

    expect(fn () => app(AddStockFromInvoice::class)->handle($this->invoice->fresh(), $this->storage, $this->owner))
        ->toThrow(ModelNotFoundException::class);

    expect($this->storage->fresh()->quantityOf($this->stockedProduct))->toBe(0)
        ->and($this->stockedLine->fresh()->delivered)->toBeFalse()
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Initial);
});
