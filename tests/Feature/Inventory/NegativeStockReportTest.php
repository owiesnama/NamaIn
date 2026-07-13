<?php

use App\Models\Preference;
use App\Models\Product;
use App\Models\Storage;
use App\Queries\Reports\NegativeStockQuery;

function makeNegative(): array
{
    Preference::create(['key' => 'inventory_strategy', 'value' => 'free_form']);
    Preference::create(['key' => 'allow_overselling', 'value' => '1']);

    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['name' => 'Widget']);
    $storage->addStock($product, 2, 'purchase_receipt');
    $storage->deductStock($product, 6, 'sale_delivery'); // balance -4

    return [$storage, $product];
}

test('the query surfaces only products below zero with driving movements', function () {
    [$storage, $product] = makeNegative();

    // A healthy product must not appear.
    $healthy = Product::factory()->create();
    $storage->addStock($healthy, 10, 'purchase_receipt');

    $rows = (new NegativeStockQuery)->get();

    expect($rows)->toHaveCount(1);
    expect($rows[0]['product_name'])->toBe('Widget');
    expect($rows[0]['quantity'])->toBe(-4);
    expect($rows[0]['days_negative'])->toBe(0);
    expect(collect($rows[0]['movements'])->pluck('quantity'))->toContain(-6);

    $summary = (new NegativeStockQuery)->summary();
    expect($summary['product_count'])->toBe(1);
    expect($summary['units_short'])->toBe(4);
});

test('the negative stock report page renders for an authorised user', function () {
    makeNegative();

    actingAsTenantUser(role: 'owner')
        ->get(route('reports.negative-stock'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/NegativeStock')
            ->has('data', 1)
            ->where('data.0.quantity', -4)
            ->where('summary.units_short', 4)
        );
});
