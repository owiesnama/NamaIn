<?php

use App\Enums\StorageType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

function latestChange(int $tenantId): ?object
{
    return DB::table('change_log')->where('tenant_id', $tenantId)->orderByDesc('seq')->first();
}

function nextSeqFor(int $tenantId): int
{
    return (int) (DB::table('change_log')->where('tenant_id', $tenantId)->max('seq') ?? 0) + 1;
}

it('records a create entry with the next seq for a syncable model', function () {
    $tenantId = app('currentTenant')->id;
    $expectedSeq = nextSeqFor($tenantId);

    $product = Product::create(['name' => 'Widget', 'cost' => 5]);

    $entry = latestChange($tenantId);
    expect($entry->table_name)->toBe('products');
    expect($entry->public_id)->toBe($product->public_id);
    expect($entry->operation)->toBe('create');
    expect((int) $entry->seq)->toBe($expectedSeq);
});

it('records exactly one entry per write and advances the seq by one', function () {
    $tenantId = app('currentTenant')->id;
    $product = Product::create(['name' => 'Widget', 'cost' => 5]);

    $countAfterCreate = DB::table('change_log')->where('tenant_id', $tenantId)->count();
    $seqAfterCreate = (int) latestChange($tenantId)->seq;

    $product->update(['name' => 'Renamed']);

    $entry = latestChange($tenantId);
    expect(DB::table('change_log')->where('tenant_id', $tenantId)->count())->toBe($countAfterCreate + 1);
    expect($entry->operation)->toBe('update');
    expect((int) $entry->seq)->toBe($seqAfterCreate + 1);
});

it('emits a delete tombstone on soft delete', function () {
    $tenantId = app('currentTenant')->id;
    $product = Product::create(['name' => 'Widget', 'cost' => 5]);

    $product->delete(); // Product uses SoftDeletes

    $entry = latestChange($tenantId);
    expect($entry->operation)->toBe('delete');
    expect($entry->public_id)->toBe($product->public_id);
    expect($entry->table_name)->toBe('products');
});

it('emits a delete tombstone on hard delete', function () {
    $tenantId = app('currentTenant')->id;
    $category = Category::create(['name' => 'Drinks']);

    $category->delete(); // Category hard-deletes

    $entry = latestChange($tenantId);
    expect($entry->operation)->toBe('delete');
    expect($entry->public_id)->toBe($category->public_id);
    expect($entry->table_name)->toBe('categories');
});

it('captures raw stocks writes through channel B', function () {
    $tenantId = app('currentTenant')->id;
    $storage = Storage::create(['name' => 'Main', 'address' => 'x', 'type' => StorageType::WAREHOUSE]);
    $product = Product::create(['name' => 'Bolt', 'cost' => 2]);

    $storage->addStock($product, 5, 'test');

    $stockPublicId = DB::table('stocks')
        ->where('storage_id', $storage->id)->where('product_id', $product->id)->value('public_id');

    $stockEntries = DB::table('change_log')
        ->where('tenant_id', $tenantId)
        ->where('table_name', 'stocks')
        ->where('public_id', $stockPublicId)
        ->get();

    expect($stockEntries)->toHaveCount(1);
    expect($stockEntries->first()->operation)->toBe('create');
});

it('keeps an independent per-tenant seq sequence', function () {
    $tenantA = Tenant::create(['name' => 'A Co', 'slug' => 'a-'.uniqid(), 'is_active' => true]);
    $tenantB = Tenant::create(['name' => 'B Co', 'slug' => 'b-'.uniqid(), 'is_active' => true]);

    app()->instance('currentTenant', $tenantA);
    $a1 = Product::create(['name' => 'A1', 'cost' => 1]);

    app()->instance('currentTenant', $tenantB);
    $b1 = Product::create(['name' => 'B1', 'cost' => 1]);

    $aEntry = DB::table('change_log')->where('tenant_id', $tenantA->id)->where('public_id', $a1->public_id)->first();
    $bEntry = DB::table('change_log')->where('tenant_id', $tenantB->id)->where('public_id', $b1->public_id)->first();

    // Each tenant's counter is independent; both freshly-created tenants share the
    // same low seq range rather than a single global sequence.
    expect((int) $aEntry->seq)->toBe((int) $bEntry->seq);
});
