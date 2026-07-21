<?php

use App\Models\Product;
use App\Services\Sync\IdempotentMutation;
use Illuminate\Support\Facades\DB;

it('runs the mutation once and records its result', function () {
    $tenantId = app('currentTenant')->id;

    $outcome = app(IdempotentMutation::class)->run(
        $tenantId,
        'key-1',
        'product.create',
        fn () => Product::create(['name' => 'Widget', 'cost' => 5]),
    );

    expect($outcome->replayed)->toBeFalse();
    expect($outcome->status)->toBe('applied');
    expect($outcome->resultPublicId)->toBe(Product::first()->public_id);
    expect(DB::table('sync_idempotency')->where('idempotency_key', 'key-1')->count())->toBe(1);
    expect(Product::count())->toBe(1);
});

it('replays a known key with zero extra writes and zero extra change-log rows', function () {
    $tenantId = app('currentTenant')->id;
    $runner = app(IdempotentMutation::class);

    $first = $runner->run($tenantId, 'key-2', 'product.create', fn () => Product::create(['name' => 'Widget', 'cost' => 5]));

    $productCount = Product::count();
    $changeCount = DB::table('change_log')->where('tenant_id', $tenantId)->count();
    $idempotencyCount = DB::table('sync_idempotency')->count();

    $mutationRan = false;
    $second = $runner->run($tenantId, 'key-2', 'product.create', function () use (&$mutationRan) {
        $mutationRan = true;

        return Product::create(['name' => 'Duplicate', 'cost' => 9]);
    });

    expect($mutationRan)->toBeFalse();
    expect($second->replayed)->toBeTrue();
    expect($second->resultPublicId)->toBe($first->resultPublicId);
    expect(Product::count())->toBe($productCount);
    expect(DB::table('change_log')->where('tenant_id', $tenantId)->count())->toBe($changeCount);
    expect(DB::table('sync_idempotency')->count())->toBe($idempotencyCount);
});

it('scopes idempotency keys per tenant', function () {
    $runner = app(IdempotentMutation::class);
    $tenantId = app('currentTenant')->id;

    $runner->run($tenantId, 'shared-key', 'product.create', fn () => Product::create(['name' => 'A', 'cost' => 1]));
    $again = $runner->run($tenantId, 'shared-key', 'product.create', fn () => Product::create(['name' => 'B', 'cost' => 1]));

    expect($again->replayed)->toBeTrue();
    expect(Product::count())->toBe(1);
});
