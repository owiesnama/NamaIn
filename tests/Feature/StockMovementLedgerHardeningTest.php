<?php

use App\Actions\Stock\RecordAdjustmentAction;
use App\Enums\MovementType;
use App\Exceptions\StockMovementIsImmutableException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Storage;
use App\Models\User;

test('recordMovement persists the typed movement_type for each write path', function (string $reason, string $direction, MovementType $expected) {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 100, 'purchase_receipt');

    match ($direction) {
        'add' => $storage->addStock($product, 5, $reason),
        'deduct' => $storage->deductStock($product, 5, $reason),
        'set' => $storage->setStockTo($product, 42, $reason),
    };

    $movement = StockMovement::where('product_id', $product->id)
        ->where('reason', $reason)
        ->latest('id')
        ->first();

    expect($movement->movement_type)->toBe($expected);
})->with([
    ['purchase_receipt', 'add', MovementType::PurchaseReceipt],
    ['invoice_addition', 'add', MovementType::InvoiceAddition],
    ['transfer_in', 'add', MovementType::TransferIn],
    ['sales_return', 'add', MovementType::SalesReturn],
    ['invoice_deduction', 'deduct', MovementType::InvoiceDeduction],
    ['sale_delivery', 'deduct', MovementType::SaleDelivery],
    ['transfer_out', 'deduct', MovementType::TransferOut],
    ['purchase_return', 'deduct', MovementType::PurchaseReturn],
    ['adjustment', 'set', MovementType::Adjustment],
]);

test('the adjustment action records a typed adjustment movement', function () {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $actor = User::factory()->create();

    app(RecordAdjustmentAction::class)
        ->handle($storage, $product, 30, 'manual', $actor);

    $movement = StockMovement::where('reason', 'adjustment')->latest('id')->first();

    expect($movement->movement_type)->toBe(MovementType::Adjustment);
});

test('stock movements are append-only: updates and deletes are rejected', function () {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 10, 'purchase_receipt');

    $movement = StockMovement::firstOrFail();

    expect(fn () => $movement->update(['quantity' => 999]))
        ->toThrow(StockMovementIsImmutableException::class);
    expect(fn () => $movement->delete())
        ->toThrow(StockMovementIsImmutableException::class);

    // Creating a new (compensating) movement is still allowed.
    $storage->addStock($product, 5, 'purchase_receipt');
    expect(StockMovement::count())->toBe(2);
});
