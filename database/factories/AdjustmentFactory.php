<?php

namespace Database\Factories;

use App\Models\Adjustment;
use App\Models\Product;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Adjustment>
 */
class AdjustmentFactory extends Factory
{
    public function definition(): array
    {
        $quantityBefore = $this->faker->numberBetween(0, 500);

        return [
            'storage_id' => Storage::factory(),
            'product_id' => Product::factory(),
            'created_by' => User::factory(),
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityBefore + $this->faker->numberBetween(-50, 100),
            'type' => $this->faker->randomElement(['increase', 'decrease', 'set']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
