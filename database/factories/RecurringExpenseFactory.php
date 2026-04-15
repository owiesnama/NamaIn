<?php

namespace Database\Factories;

use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpense>
 */
class RecurringExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => 'SDG',
            'notes' => $this->faker->paragraph(),
            'frequency' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'yearly']),
            'starts_at' => now(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
