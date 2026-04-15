<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->customer = Customer::factory()->create();
    $this->product = Product::factory()->create();
    $this->unit = Unit::factory()->create(['product_id' => $this->product->id]);
});

it('rejects negative total for invoice', function () {
    $this->actingAs($this->user)
        ->post(route('sales.store'), [
            'total' => -100,
            'invocable' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'products' => [
                [
                    'product' => $this->product->id,
                    'quantity' => 1,
                    'unit' => $this->unit->id,
                    'price' => 100,
                ],
            ],
        ])
        ->assertSessionHasErrors('total');
});

it('rejects negative or zero quantity for invoice items', function () {
    $this->actingAs($this->user)
        ->post(route('sales.store'), [
            'total' => 100,
            'invocable' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'products' => [
                [
                    'product' => $this->product->id,
                    'quantity' => 0,
                    'unit' => $this->unit->id,
                    'price' => 100,
                ],
            ],
        ])
        ->assertSessionHasErrors('products.0.quantity');

    $this->actingAs($this->user)
        ->post(route('sales.store'), [
            'total' => 100,
            'invocable' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'products' => [
                [
                    'product' => $this->product->id,
                    'quantity' => -1,
                    'unit' => $this->unit->id,
                    'price' => 100,
                ],
            ],
        ])
        ->assertSessionHasErrors('products.0.quantity');
});

it('rejects negative price for invoice items', function () {
    $this->actingAs($this->user)
        ->post(route('sales.store'), [
            'total' => -100,
            'invocable' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'products' => [
                [
                    'product' => $this->product->id,
                    'quantity' => 1,
                    'unit' => $this->unit->id,
                    'price' => -100,
                ],
            ],
        ])
        ->assertSessionHasErrors('products.0.price');
});

it('rejects negative discount or initial payment', function () {
    $this->actingAs($this->user)
        ->post(route('sales.store'), [
            'total' => 100,
            'invocable' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'products' => [
                [
                    'product' => $this->product->id,
                    'quantity' => 1,
                    'unit' => $this->unit->id,
                    'price' => 100,
                ],
            ],
            'discount' => -10,
            'initial_payment_amount' => -20,
        ])
        ->assertSessionHasErrors(['discount', 'initial_payment_amount']);
});
