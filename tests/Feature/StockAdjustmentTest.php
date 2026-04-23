<?php

use App\Actions\Stock\RecordAdjustmentAction;
use App\Models\Adjustment;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $this->tenant);

    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner']);

    $this->cashier = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->cashier, ['role' => 'cashier']);

    $this->storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->storage->addStock($this->product, 100, 'initial_stock', actor: $this->owner);
});

test('it can record a stock adjustment', function () {
    $adjustment = app(RecordAdjustmentAction::class)->execute(
        $this->storage,
        $this->product,
        120,
        'manual',
        $this->owner,
        'Found more items'
    );

    expect($adjustment->quantity_before)->toBe(100);
    expect($adjustment->quantity_after)->toBe(120);
    expect($this->storage->fresh()->quantityOf($this->product))->toBe(120);

    // Verify ledger entry
    $this->assertDatabaseHas('stock_movements', [
        'storage_id' => $this->storage->id,
        'product_id' => $this->product->id,
        'quantity' => 20,
        'reason' => 'adjustment',
        'movable_id' => $adjustment->id,
        'movable_type' => Adjustment::class,
    ]);
});

test('it can record a negative adjustment', function () {
    app(RecordAdjustmentAction::class)->execute(
        $this->storage,
        $this->product,
        80,
        'damage',
        $this->owner
    );

    expect($this->storage->fresh()->quantityOf($this->product))->toBe(80);

    // Verify ledger entry
    $this->assertDatabaseHas('stock_movements', [
        'storage_id' => $this->storage->id,
        'product_id' => $this->product->id,
        'quantity' => -20,
        'reason' => 'adjustment',
    ]);
});

test('cashier cannot record adjustments', function () {
    expect($this->cashier->can('create', Adjustment::class))->toBeFalse();
});
