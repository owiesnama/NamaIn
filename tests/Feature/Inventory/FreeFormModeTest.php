<?php

use App\Actions\Stock\RecordAdjustmentAction;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ManualStockIncreaseNotAllowedException;
use App\Models\Preference;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Storage;
use App\Models\User;

function useStrategy(string $strategy, bool $allowOverselling = false): void
{
    Preference::updateOrCreate(['key' => 'inventory_strategy'], ['value' => $strategy]);
    Preference::updateOrCreate(['key' => 'allow_overselling'], ['value' => $allowOverselling ? '1' : '0']);
}

test('purchase-driven blocks a sale that would drive stock negative', function () {
    // No preference set → purchase_driven (matches current behaviour).
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 5, 'purchase_receipt');

    expect(fn () => $storage->deductStock($product, 8, 'sale_delivery'))
        ->toThrow(InsufficientStockException::class);

    expect($storage->quantityOf($product))->toBe(5); // rolled back, untouched
});

test('free-form with overselling on drives the balance negative and ledgers it', function () {
    useStrategy('free_form', allowOverselling: true);
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 5, 'purchase_receipt');

    $storage->deductStock($product, 8, 'sale_delivery');

    expect($storage->quantityOf($product))->toBe(-3);

    $movement = StockMovement::where('reason', 'sale_delivery')->latest('id')->first();
    expect($movement->quantity)->toBe(-8);
    expect($movement->quantity_before)->toBe(5);
    expect($movement->quantity_after)->toBe(-3);
});

test('free-form with overselling off still blocks at zero', function () {
    useStrategy('free_form', allowOverselling: false);
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 5, 'purchase_receipt');

    expect(fn () => $storage->deductStock($product, 8, 'sale_delivery'))
        ->toThrow(InsufficientStockException::class);

    expect($storage->quantityOf($product))->toBe(5);
});

test('overselling can sell a product that has no stock row at all', function () {
    useStrategy('free_form', allowOverselling: true);
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();

    $storage->deductStock($product, 4, 'sale_delivery');

    expect($storage->quantityOf($product))->toBe(-4);
});

test('purchase-driven blocks a manual upward adjustment', function () {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $actor = User::factory()->create();
    $storage->addStock($product, 10, 'purchase_receipt');

    expect(fn () => app(RecordAdjustmentAction::class)->handle($storage, $product, 25, 'manual', $actor))
        ->toThrow(ManualStockIncreaseNotAllowedException::class);

    expect($storage->quantityOf($product))->toBe(10);
});

test('purchase-driven still allows a downward adjustment for loss or damage', function () {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $actor = User::factory()->create();
    $storage->addStock($product, 10, 'purchase_receipt');

    app(RecordAdjustmentAction::class)->handle($storage, $product, 4, 'loss', $actor);

    expect($storage->quantityOf($product))->toBe(4);
});

test('free-form allows a manual upward adjustment', function () {
    useStrategy('free_form');
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $actor = User::factory()->create();
    $storage->addStock($product, 10, 'purchase_receipt');

    app(RecordAdjustmentAction::class)->handle($storage, $product, 25, 'manual', $actor);

    expect($storage->quantityOf($product))->toBe(25);
});
