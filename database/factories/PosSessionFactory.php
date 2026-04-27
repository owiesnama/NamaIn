<?php

namespace Database\Factories;

use App\Models\PosSession;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSession>
 */
class PosSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'storage_id' => Storage::factory(),
            'opened_by' => User::factory(),
            'opening_float' => $this->faker->numberBetween(1000, 50000),
        ];
    }

    public function closed(): static
    {
        return $this->state([
            'closed_by' => User::factory(),
            'closing_float' => $this->faker->numberBetween(5000, 100000),
            'closed_at' => now(),
        ]);
    }
}
