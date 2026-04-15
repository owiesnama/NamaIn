<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Transaction;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'storage_id' => Storage::factory(),
            'invoice_id' => Invoice::factory(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'base_quantity' => function (array $attributes) {
                return $attributes['quantity'];
            },
            'unit_id' => function (array $attributes) {
                return Unit::factory()->create(['product_id' => $attributes['product_id']])->id;
            },
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'delivered' => $this->faker->boolean(),
            'currency' => 'SDG',
        ];
    }
}
