<?php

use App\Enums\StorageType;
use App\Models\Storage;

test('storage has default type warehouse', function () {
    $storage = Storage::factory()->create();
    $storage = $storage->fresh();
    expect($storage->type)->toBe(StorageType::WAREHOUSE);
    expect($storage->isWarehouse())->toBeTrue();
});

test('storage can be a sale point', function () {
    $storage = Storage::factory()->create(['type' => StorageType::SALE_POINT]);
    $storage = $storage->fresh();
    expect($storage->type)->toBe(StorageType::SALE_POINT);
    expect($storage->isSalePoint())->toBeTrue();
});

test('storage scopes work correctly', function () {
    Storage::factory()->create(['type' => StorageType::WAREHOUSE]);
    Storage::factory()->create(['type' => StorageType::SALE_POINT]);

    expect(Storage::warehouses()->count())->toBeGreaterThanOrEqual(1);
    expect(Storage::salePoints()->count())->toBeGreaterThanOrEqual(1);

    Storage::warehouses()->get()->each(function ($storage) {
        expect($storage->isWarehouse())->toBeTrue();
    });

    Storage::salePoints()->get()->each(function ($storage) {
        expect($storage->isSalePoint())->toBeTrue();
    });
});
