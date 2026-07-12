<?php

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Storage;
use App\Queries\StockBalanceQuery;
use Illuminate\Support\Facades\DB;

function seedBypassedStock(Storage $storage, Product $product, int $quantity): void
{
    DB::table('stocks')->insert([
        'tenant_id' => $storage->tenant_id,
        'storage_id' => $storage->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('backfill creates an opening-balance movement for a bypassed stocks row', function () {
    $tenant = app('currentTenant');
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    seedBypassedStock($storage, $product, 60);

    $this->artisan('stock:backfill-opening-balances', ['--tenant' => $tenant->slug])->assertExitCode(0);

    $movement = StockMovement::where('reason', 'opening_balance')->where('product_id', $product->id)->first();
    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe(60);
    expect($movement->quantity_before)->toBe(0);
    expect($movement->quantity_after)->toBe(60);
    expect($movement->movement_type)->toBe(MovementType::OpeningBalance);

    // Ledger now reconstructs the cache exactly.
    expect((new StockBalanceQuery)->forProductInStorage($product->id, $storage->id))->toBe(60);

    // Idempotent: a second run inserts nothing.
    $this->artisan('stock:backfill-opening-balances', ['--tenant' => $tenant->slug])->assertExitCode(0);
    expect(StockMovement::where('reason', 'opening_balance')->count())->toBe(1);
});

test('backfill is a no-op when the ledger already matches the cache', function () {
    $tenant = app('currentTenant');
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 40, 'purchase_receipt');

    $this->artisan('stock:backfill-opening-balances', ['--tenant' => $tenant->slug])->assertExitCode(0);

    expect(StockMovement::where('reason', 'opening_balance')->count())->toBe(0);
});

test('dry-run reports the gap without writing a movement', function () {
    $tenant = app('currentTenant');
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    seedBypassedStock($storage, $product, 60);

    $this->artisan('stock:backfill-opening-balances', ['--dry-run' => true, '--tenant' => $tenant->slug])->assertExitCode(0);

    expect(StockMovement::where('reason', 'opening_balance')->count())->toBe(0);
});
