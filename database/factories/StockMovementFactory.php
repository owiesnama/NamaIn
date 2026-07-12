<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        $quantityBefore = $this->faker->numberBetween(0, 500);
        $quantity = $this->faker->numberBetween(-100, 100);
        $reason = $this->faker->randomElement(['invoice_deduction', 'invoice_addition', 'adjustment', 'transfer_in', 'sales_return', 'purchase_return']);

        return [
            'storage_id' => Storage::factory(),
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'reason' => $reason,
            'movement_type' => MovementType::fromReason($reason),
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityBefore + $quantity,
        ];
    }
}
