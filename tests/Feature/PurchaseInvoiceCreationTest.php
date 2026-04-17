<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Invoice will be created for every purchase', function () {
    $product = Product::factory()->create();

    $this->assertDatabaseCount(Invoice::class, 0);

    $this->signIn()
        ->post(route('purchases.store'), [
            'total' => 200,
            'invocable' => Customer::factory()->create()->toArray(),
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
