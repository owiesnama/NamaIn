<?php

use App\Actions\Stock\RecordAdjustmentAction;
use App\Models\Expense;
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
    $this->actingAs($this->owner);

    $this->storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'cost' => 50]);

    $this->storage->addStock($this->product, 100, 'initial_stock', actor: $this->owner);
});

test('downward adjustment creates an expense for the inventory loss', function () {
    $adjustment = app(RecordAdjustmentAction::class)->execute(
        $this->storage,
        $this->product,
        80,
        'damage',
        $this->owner,
        'Damaged goods'
    );

    $lossQuantity = 100 - 80;
    $expectedAmount = round($lossQuantity * $this->product->average_cost, 2);

    expect($adjustment->expense_id)->not->toBeNull();
    expect($adjustment->expense)->toBeInstanceOf(Expense::class);
    expect((float) $adjustment->expense->amount)->toBe($expectedAmount);
    expect($adjustment->expense->title)->toContain('Inventory write-down');
    expect($adjustment->expense->title)->toContain($this->product->name);
    expect($adjustment->expense->title)->toContain('damage');
    expect($adjustment->expense->created_by)->toBe($this->owner->id);
});

test('upward adjustment does not create an expense', function () {
    $adjustment = app(RecordAdjustmentAction::class)->execute(
        $this->storage,
        $this->product,
        120,
        'manual',
        $this->owner,
        'Found extra items'
    );

    expect($adjustment->expense_id)->toBeNull();
    expect(Expense::count())->toBe(0);
});

test('write-down uses weighted average cost from purchase history', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
    $invoice = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
        'total' => 1000,
        'tenant_id' => $this->tenant->id,
    ]);

    Transaction::create([
        'invoice_id' => $invoice->id,
        'product_id' => $this->product->id,
        'storage_id' => $this->storage->id,
        'quantity' => 50,
        'base_quantity' => 50,
        'price' => 20,
        'unit_cost' => 20,
        'delivered' => true,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->product->refresh();
    $avgCost = $this->product->average_cost;

    $adjustment = app(RecordAdjustmentAction::class)->execute(
        $this->storage,
        $this->product,
        90,
        'loss',
        $this->owner,
    );

    $expectedAmount = round(10 * $avgCost, 2);
    expect((float) $adjustment->expense->amount)->toBe($expectedAmount);
});

test('no expense created when product has zero average cost', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'cost' => 0]);
    $this->storage->addStock($product, 50, 'initial_stock', actor: $this->owner);

    $adjustment = app(RecordAdjustmentAction::class)->execute(
        $this->storage,
        $product,
        40,
        'loss',
        $this->owner,
    );

    expect($adjustment->expense_id)->toBeNull();
    expect(Expense::count())->toBe(0);
});
