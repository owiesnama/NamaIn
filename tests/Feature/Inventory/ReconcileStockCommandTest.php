<?php

use App\Models\Product;
use App\Models\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('reconcile reports no drift and exits zero on clean data', function () {
    $tenant = app('currentTenant');
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();
    $storage->addStock($product, 50, 'purchase_receipt');
    $storage->deductStock($product, 20, 'sale_delivery');

    $this->artisan('stock:reconcile', ['--tenant' => $tenant->slug])
        ->assertExitCode(0);
});

test('reconcile detects a bypassed stocks row as drift and exits non-zero', function () {
    $tenant = app('currentTenant');
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();

    // Simulate a seeder-style bypass: write the cache without a movement.
    DB::table('stocks')->insert([
        'tenant_id' => $tenant->id,
        'storage_id' => $storage->id,
        'product_id' => $product->id,
        'quantity' => 60,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('stock:reconcile', ['--tenant' => $tenant->slug])
        ->assertExitCode(1);
});

test('reconcile --json emits the exact drift for a bypassed row', function () {
    $tenant = app('currentTenant');
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();

    DB::table('stocks')->insert([
        'tenant_id' => $tenant->id,
        'storage_id' => $storage->id,
        'product_id' => $product->id,
        'quantity' => 60,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('stock:reconcile', ['--json' => true, '--tenant' => $tenant->slug]);
    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toHaveCount(1);
    expect($payload[0])->toMatchArray([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'cache' => 60,
        'ledger' => 0,
        'drift' => 60,
    ]);
});
