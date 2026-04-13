<?php

use App\Models\Product;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('Invoice will be created for every purchase', function () {
    $product = Product::factory()->create();

    $this->assertDatabaseCount(\App\Models\Invoice::class, 0);

    $this->signIn()
        ->post(route('purchases.store'), [
            'total' => 200,
            'invocable' => \App\Models\Customer::factory()->create()->toArray(),
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

    $this->assertDatabaseCount(\App\Models\Invoice::class, 1);

});
/* @TODO
 * Create for validations.
 */
