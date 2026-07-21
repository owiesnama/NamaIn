<?php

use App\Enums\ProductType;
use App\Exports\ProductExport;
use App\Jobs\GenerateExportJob;
use App\Models\ExportLog;
use App\Models\Product;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Bulk delete
|--------------------------------------------------------------------------
*/

test('bulk delete soft-deletes only the selected products', function () {
    $user = User::factory()->create();
    $selected = Product::factory()->count(2)->create();
    $untouched = Product::factory()->create();

    $this->actingAs($user)
        ->delete(route('products.bulk.destroy'), ['ids' => $selected->pluck('id')->all()])
        ->assertRedirect();

    $selected->each(fn (Product $product) => $this->assertSoftDeleted($product));
    expect($untouched->fresh()->trashed())->toBeFalse();
});

test('bulk delete requires the products.delete permission', function () {
    $product = Product::factory()->create();

    actingAsTenantUser(role: 'staff')
        ->delete(route('products.bulk.destroy'), ['ids' => [$product->id]])
        ->assertForbidden();
});

test('bulk delete rejects an empty id list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('products.bulk.destroy'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});

/*
|--------------------------------------------------------------------------
| Bulk update price
|--------------------------------------------------------------------------
*/

test('bulk price set assigns the absolute value to every selected product', function () {
    $user = User::factory()->create();
    $products = Product::factory()->count(2)->create(['price' => 100]);

    $this->actingAs($user)
        ->patch(route('products.bulk.price'), [
            'ids' => $products->pluck('id')->all(),
            'mode' => 'set',
            'value' => 250,
        ])
        ->assertRedirect();

    $products->each(fn (Product $product) => expect($product->fresh()->price)->toBe(250.0));
});

test('bulk price percent increases each price and rounds', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100]);

    $this->actingAs($user)
        ->patch(route('products.bulk.price'), [
            'ids' => [$product->id],
            'mode' => 'percent',
            'value' => 10,
        ])
        ->assertRedirect();

    expect($product->fresh()->price)->toBe(110.0);
});

test('bulk price percent can decrease and never drops below zero', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100]);

    $this->actingAs($user)
        ->patch(route('products.bulk.price'), [
            'ids' => [$product->id],
            'mode' => 'percent',
            'value' => -25,
        ])
        ->assertRedirect();

    expect($product->fresh()->price)->toBe(75.0);
});

test('bulk price rejects an unknown mode', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('products.bulk.price'), [
            'ids' => [Product::factory()->create()->id],
            'mode' => 'multiply',
            'value' => 2,
        ])
        ->assertSessionHasErrors('mode');
});

test('bulk price requires the products.update permission', function () {
    actingAsTenantUser(role: 'staff')
        ->patch(route('products.bulk.price'), [
            'ids' => [Product::factory()->create()->id],
            'mode' => 'set',
            'value' => 10,
        ])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Bulk adjust stock
|--------------------------------------------------------------------------
*/

test('bulk stock applies a delta to the selected products at the storage', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $product->stock()->attach($storage->id, ['quantity' => 10, 'public_id' => strtolower((string) Str::ulid())]);

    $this->actingAs($user)
        ->post(route('products.bulk.stock'), [
            'ids' => [$product->id],
            'storage_id' => $storage->id,
            'delta' => -3,
            'type' => 'manual',
        ])
        ->assertRedirect();

    expect($storage->quantityOf($product))->toBe(7);
});

test('bulk stock skips service products', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $physical = Product::factory()->create();
    $physical->stock()->attach($storage->id, ['quantity' => 10, 'public_id' => strtolower((string) Str::ulid())]);
    $service = Product::factory()->create(['type' => ProductType::Service]);

    $this->actingAs($user)
        ->post(route('products.bulk.stock'), [
            'ids' => [$physical->id, $service->id],
            'storage_id' => $storage->id,
            'delta' => -2,
            'type' => 'manual',
        ])
        ->assertRedirect();

    expect($storage->quantityOf($physical))->toBe(8);
});

test('bulk stock skips products that violate the inventory strategy without failing the batch', function () {
    // Default strategy is purchase-driven: manual increases are not allowed.
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $product->stock()->attach($storage->id, ['quantity' => 10, 'public_id' => strtolower((string) Str::ulid())]);

    $this->actingAs($user)
        ->post(route('products.bulk.stock'), [
            'ids' => [$product->id],
            'storage_id' => $storage->id,
            'delta' => 5,
            'type' => 'manual',
        ])
        ->assertRedirect();

    expect($storage->quantityOf($product))->toBe(10);
});

test('bulk stock rejects a zero delta', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();

    $this->actingAs($user)
        ->post(route('products.bulk.stock'), [
            'ids' => [Product::factory()->create()->id],
            'storage_id' => $storage->id,
            'delta' => 0,
            'type' => 'manual',
        ])
        ->assertSessionHasErrors('delta');
});

/*
|--------------------------------------------------------------------------
| Bulk export
|--------------------------------------------------------------------------
*/

test('bulk export queues an export scoped to the selected ids', function () {
    Queue::fake();
    $user = User::factory()->create();
    $products = Product::factory()->count(2)->create();
    $ids = $products->pluck('id')->all();

    $this->actingAs($user)
        ->post(route('products.bulk.export'), ['ids' => $ids])
        ->assertRedirect();

    Queue::assertPushed(GenerateExportJob::class);

    $log = ExportLog::latest('id')->first();
    expect($log->export_key)->toBe('products');
    expect($log->filters['ids'])->toBe($ids);
});

test('product export collection only returns the selected ids', function () {
    User::factory()->create(); // resolves tenant context for the query scope
    $wanted = Product::factory()->count(2)->create();
    Product::factory()->create(); // excluded

    $rows = (new ProductExport(['ids' => $wanted->pluck('id')->all()]))->collection();

    expect($rows->pluck('id')->sort()->values()->all())
        ->toBe($wanted->pluck('id')->sort()->values()->all());
});
