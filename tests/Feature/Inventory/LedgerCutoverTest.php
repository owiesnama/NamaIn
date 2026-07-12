<?php

use App\Actions\Stock\TransferStockAction;
use App\Models\Preference;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Storage;
use App\Models\User;
use App\Queries\Reports\InventoryValuationQuery;
use App\Queries\StockBalanceQuery;

test('the stocks cache equals the ledger through the full lifecycle including overselling', function () {
    Preference::create(['key' => 'inventory_strategy', 'value' => 'free_form']);
    Preference::create(['key' => 'allow_overselling', 'value' => '1']);

    $a = Storage::factory()->create();
    $b = Storage::factory()->create();
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $a->addStock($product, 50, 'purchase_receipt', actor: $user);
    $a->deductStock($product, 20, 'sale_delivery', actor: $user);
    $a->setStockTo($product, 40, 'adjustment', actor: $user);

    $transfer = StockTransfer::factory()->create([
        'from_storage_id' => $a->id, 'to_storage_id' => $b->id, 'created_by' => $user->id,
    ]);
    StockTransferLine::factory()->create([
        'stock_transfer_id' => $transfer->id, 'product_id' => $product->id, 'quantity' => 10,
    ]);
    app(TransferStockAction::class)->handle($transfer, $user);

    $b->deductStock($product, 25, 'sale_delivery', actor: $user); // b: 10 -> -15 (oversell)
    $a->addStock($product, 5, 'sales_return', actor: $user);

    $balance = new StockBalanceQuery;
    foreach ([$a, $b] as $storage) {
        expect($balance->forProductInStorage($product->id, $storage->id))->toBe($storage->quantityOf($product));
    }

    expect($product->ledgerBalance())->toBe($a->quantityOf($product) + $b->quantityOf($product));
    expect($b->quantityOf($product))->toBe(-15);
});

test('inventory valuation includes negative (oversold) balances', function () {
    Preference::create(['key' => 'inventory_strategy', 'value' => 'free_form']);
    Preference::create(['key' => 'allow_overselling', 'value' => '1']);

    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['name' => 'Oversold', 'average_cost' => 1000]);
    $storage->deductStock($product, 4, 'sale_delivery'); // -4

    $row = collect((new InventoryValuationQuery)->get())->firstWhere('product_name', 'Oversold');

    expect($row)->not->toBeNull();
    expect($row['quantity'])->toBe(-4);
    expect($row['total_value'])->toBeLessThan(0);
});
