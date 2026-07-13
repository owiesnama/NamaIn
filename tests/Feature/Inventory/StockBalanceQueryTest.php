<?php

use App\Actions\Stock\TransferStockAction;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;
use App\Queries\StockBalanceQuery;

test('ledger balance equals the cache for every product and storage on clean data', function () {
    $query = new StockBalanceQuery;
    $storageA = Storage::factory()->create();
    $storageB = Storage::factory()->create();
    $product = Product::factory()->create();
    $user = User::factory()->create();

    // A mixed sequence through the write choke point.
    $storageA->addStock($product, 100, 'purchase_receipt', actor: $user);
    $storageA->deductStock($product, 30, 'sale_delivery', actor: $user);
    $storageA->setStockTo($product, 80, 'adjustment', actor: $user);

    // A real transfer: net-zero across two storages.
    $transfer = StockTransfer::factory()->create([
        'from_storage_id' => $storageA->id,
        'to_storage_id' => $storageB->id,
        'created_by' => $user->id,
    ]);
    StockTransferLine::factory()->create([
        'stock_transfer_id' => $transfer->id,
        'product_id' => $product->id,
        'quantity' => 20,
    ]);
    app(TransferStockAction::class)->handle($transfer, $user);

    // A return raises stock back.
    $storageA->addStock($product, 5, 'sales_return', actor: $user);

    // Invariant: ledger sum == cache, per (product, storage).
    foreach ([$storageA, $storageB] as $storage) {
        $cache = $storage->quantityOf($product);
        expect($query->forProductInStorage($product->id, $storage->id))->toBe($cache);
    }

    // And the whole-product ledger balance matches the summed cache.
    $totalCache = $storageA->quantityOf($product) + $storageB->quantityOf($product);
    expect($query->forProduct($product->id))->toBe($totalCache);
    expect($totalCache)->toBe(85); // 100 -30 +10 (-20 +20 net) +5
});

test('the balance helper is tenant scoped', function () {
    $query = new StockBalanceQuery;
    $currentTenant = app('currentTenant');

    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 40, 'purchase_receipt');

    // Another tenant with its own movements for a product of the SAME id space.
    $other = Tenant::create(['name' => 'Other Org', 'slug' => 'other-org', 'is_active' => true]);
    app()->instance('currentTenant', $other);
    $otherStorage = Storage::factory()->create();
    $otherProduct = Product::factory()->create();
    $otherStorage->addStock($otherProduct, 999, 'purchase_receipt');

    // Back to the original tenant: its balance excludes the other tenant's rows.
    app()->instance('currentTenant', $currentTenant);
    expect($query->forProduct($product->id))->toBe(40);
    expect(StockMovement::count())->toBe(1);
});

test('a product with no movements has a zero ledger balance', function () {
    $product = Product::factory()->create();

    expect((new StockBalanceQuery)->forProduct($product->id))->toBe(0);
});
