<?php

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Supplier;

test('Invoice will be created for every purchase', function () {
    $product = Product::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->assertDatabaseCount(Invoice::class, 0);

    actingAsTenantUser()
        ->post(route('purchases.store'), [
            'total' => 200,
            'invocable' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'type' => Supplier::class,
            ],
            'products' => [[
                'product' => $product->id,
                'price' => $product->cost + 100,
                'unit' => $product->units()->create([
                    'name' => 'box',
                    'conversion_factor' => 1,
                ])->id,
                'quantity' => 5,
                'description' => 'Test purchase',
            ]],
        ])->assertRedirectToRoute('purchases.index');

    $this->assertDatabaseCount(Invoice::class, 1);
});
/* @TODO
 * Create for validations.
 */
