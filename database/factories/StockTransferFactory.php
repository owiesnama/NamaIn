<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'from_storage_id' => Storage::factory(),
            'to_storage_id' => Storage::factory(),
            'created_by' => User::factory(),
            'notes' => $this->faker->optional()->sentence(),
            'transferred_at' => $this->faker->optional()->dateTimeThisMonth(),
        ];
    }

    public function transferred(): static
    {
        return $this->state([
            'transferred_at' => now(),
        ]);
    }
}
