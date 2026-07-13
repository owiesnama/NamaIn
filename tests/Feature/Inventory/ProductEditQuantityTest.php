<?php

use App\Models\Preference;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Storage;
use App\Models\User;

test('editing on-hand quantities records the difference per storage as adjustments', function () {
    Preference::create(['key' => 'inventory_strategy', 'value' => 'free_form']);
    $a = Storage::factory()->create();
    $b = Storage::factory()->create();
    $product = Product::factory()->create();
    $a->addStock($product, 30, 'purchase_receipt');
    $b->addStock($product, 5, 'purchase_receipt');

    $this->actingAs(User::factory()->create())
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'cost' => 10,
            'units' => [['name' => 'Box', 'conversion_factor' => 1]],
            'categories' => [],
            'quantities' => [
                ['storage_id' => $a->id, 'quantity' => 50], // +20
                ['storage_id' => $b->id, 'quantity' => 5],  // unchanged → no-op
            ],
        ])->assertSessionHasNoErrors();

    expect($a->fresh()->quantityOf($product))->toBe(50);
    expect($b->fresh()->quantityOf($product))->toBe(5);

    // Only storage A drifted, so exactly one adjustment movement.
    expect(StockMovement::where('reason', 'adjustment')->count())->toBe(1);
    $this->assertDatabaseHas('adjustments', [
        'product_id' => $product->id,
        'storage_id' => $a->id,
        'type' => 'product_edit',
        'quantity_before' => 30,
        'quantity_after' => 50,
    ]);
});

test('creating a product with per-storage quantities sets stock via adjustments', function () {
    Preference::create(['key' => 'inventory_strategy', 'value' => 'free_form']);
    $storage = Storage::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('products.index'), [
            'name' => 'Seeded Product',
            'cost' => 10,
            'units' => [['name' => 'Box', 'conversion_factor' => 1]],
            'categories' => [],
            'quantities' => [
                ['storage_id' => $storage->id, 'quantity' => 40],
            ],
        ])->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Seeded Product')->firstOrFail();
    expect($storage->fresh()->quantityOf($product))->toBe(40);
    $this->assertDatabaseHas('adjustments', [
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'type' => 'product_edit',
        'quantity_after' => 40,
    ]);
});

test('editing quantities upward is blocked under purchase-driven', function () {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 30, 'purchase_receipt');

    $this->actingAs(User::factory()->create())
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'cost' => 10,
            'units' => [['name' => 'Box', 'conversion_factor' => 1]],
            'categories' => [],
            'quantities' => [
                ['storage_id' => $storage->id, 'quantity' => 50],
            ],
        ])->assertSessionHas('error');

    expect($storage->fresh()->quantityOf($product))->toBe(30);
});

test('editing quantities to the same values records no adjustment', function () {
    Preference::create(['key' => 'inventory_strategy', 'value' => 'free_form']);
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 30, 'purchase_receipt');

    $this->actingAs(User::factory()->create())
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'cost' => 10,
            'units' => [['name' => 'Box', 'conversion_factor' => 1]],
            'categories' => [],
            'quantities' => [
                ['storage_id' => $storage->id, 'quantity' => 30],
            ],
        ])->assertSessionHasNoErrors();

    expect(StockMovement::where('reason', 'adjustment')->count())->toBe(0);
});

test('updating a product without quantities leaves stock untouched', function () {
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 30, 'purchase_receipt');

    $this->actingAs(User::factory()->create())
        ->put(route('products.update', $product), [
            'name' => 'Renamed Product',
            'cost' => 15,
            'units' => [['name' => 'Box', 'conversion_factor' => 1]],
            'categories' => [],
        ])->assertSessionHasNoErrors();

    expect($storage->fresh()->quantityOf($product))->toBe(30);
    expect($product->fresh()->name)->toBe('Renamed Product');
});
