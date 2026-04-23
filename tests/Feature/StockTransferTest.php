<?php

use App\Actions\Stock\ExecuteStockTransferAction;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
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

    $this->fromStorage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->toStorage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->fromStorage->addStock($this->product, 100, 'initial_stock', actor: $this->owner);
});

test('it can execute a stock transfer successfully', function () {
    $transfer = StockTransfer::create([
        'tenant_id' => $this->tenant->id,
        'from_storage_id' => $this->fromStorage->id,
        'to_storage_id' => $this->toStorage->id,
        'created_by' => $this->owner->id,
    ]);

    StockTransferLine::create([
        'tenant_id' => $this->tenant->id,
        'stock_transfer_id' => $transfer->id,
        'product_id' => $this->product->id,
        'quantity' => 40,
    ]);

    expect(StockTransferLine::where('stock_transfer_id', $transfer->id)->count())->toBe(1);

    $action = app(ExecuteStockTransferAction::class);
    $action->execute($transfer, $this->owner);

    expect(DB::table('stocks')->where('storage_id', $this->fromStorage->id)->where('product_id', $this->product->id)->value('quantity'))->toBe(60);
    expect(DB::table('stocks')->where('storage_id', $this->toStorage->id)->where('product_id', $this->product->id)->value('quantity'))->toBe(40);
    expect($transfer->fresh()->transferred_at)->not->toBeNull();

    // Verify ledger entries
    $this->assertDatabaseHas('stock_movements', [
        'storage_id' => $this->fromStorage->id,
        'product_id' => $this->product->id,
        'quantity' => -40,
        'reason' => 'transfer_out',
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'storage_id' => $this->toStorage->id,
        'product_id' => $this->product->id,
        'quantity' => 40,
        'reason' => 'transfer_in',
    ]);
});

test('it fails if insufficient stock in source', function () {
    $transfer = StockTransfer::create([
        'tenant_id' => $this->tenant->id,
        'from_storage_id' => $this->fromStorage->id,
        'to_storage_id' => $this->toStorage->id,
        'created_by' => $this->owner->id,
    ]);

    StockTransferLine::create([
        'tenant_id' => $this->tenant->id,
        'stock_transfer_id' => $transfer->id,
        'product_id' => $this->product->id,
        'quantity' => 150, // More than 100 available
    ]);

    expect(fn () => app(ExecuteStockTransferAction::class)->execute($transfer, $this->owner))
        ->toThrow(InsufficientStockException::class);

    // Verify rollback
    expect($this->fromStorage->fresh()->quantityOf($this->product))->toBe(100);
    expect($this->toStorage->fresh()->quantityOf($this->product))->toBe(0);
});

test('it fails if source and destination are same', function () {
    $transfer = StockTransfer::create([
        'tenant_id' => $this->tenant->id,
        'from_storage_id' => $this->fromStorage->id,
        'to_storage_id' => $this->fromStorage->id,
        'created_by' => $this->owner->id,
    ]);

    expect(fn () => app(ExecuteStockTransferAction::class)->execute($transfer, $this->owner))
        ->toThrow(InvalidArgumentException::class);
});

test('cashier cannot view or create transfers', function () {
    expect($this->cashier->can('viewAny', StockTransfer::class))->toBeFalse();
    expect($this->cashier->can('create', StockTransfer::class))->toBeFalse();
});
