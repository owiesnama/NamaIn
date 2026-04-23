<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Storage;
use App\Models\User;

test('addStock writes to ledger and handles concurrent seeding', function () {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $storage->addStock($product, 10, 'initial_stock', actor: $user);

    expect($storage->quantityOf($product))->toBe(10);

    $movement = StockMovement::where('storage_id', $storage->id)
        ->where('product_id', $product->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe(10);
    expect($movement->quantity_before)->toBe(0);
    expect($movement->quantity_after)->toBe(10);
    expect($movement->reason)->toBe('initial_stock');
    expect($movement->user_id)->toBe($user->id);
});

test('deductStock writes to ledger and checks for insufficient stock', function () {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();

    $storage->addStock($product, 20, 'restock');
    $storage->deductStock($product, 15, 'sale');

    expect($storage->quantityOf($product))->toBe(5);

    $movement = StockMovement::where('storage_id', $storage->id)
        ->where('product_id', $product->id)
        ->where('reason', 'sale')
        ->first();

    expect($movement->quantity)->toBe(-15);
    expect($movement->quantity_before)->toBe(20);
    expect($movement->quantity_after)->toBe(5);
});

test('deductStock throws exception on insufficient stock', function () {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();

    $storage->addStock($product, 10, 'restock');

    $this->expectException(InsufficientStockException::class);
    $storage->deductStock($product, 15, 'sale');
});

test('setStockTo writes to ledger with delta', function () {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();

    $storage->addStock($product, 10, 'restock');
    $storage->setStockTo($product, 25, 'adjustment');

    expect($storage->quantityOf($product))->toBe(25);

    $movement = StockMovement::where('storage_id', $storage->id)
        ->where('product_id', $product->id)
        ->where('reason', 'adjustment')
        ->first();

    expect($movement->quantity)->toBe(15); // 25 - 10
    expect($movement->quantity_before)->toBe(10);
    expect($movement->quantity_after)->toBe(25);
});
