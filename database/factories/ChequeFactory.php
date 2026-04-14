<?php

namespace Database\Factories;

use App\Enums\ChequeStatus;
use App\Models\Cheque;
/**
 * @extends Factory<Cheque>
 */
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChequeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chequeable_id' => Customer::factory(),
            'chequeable_type' => Customer::class,
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'type' => $this->faker->randomElement([0, 1]),
            'bank' => $this->faker->company(),
            'status' => ChequeStatus::Drafted,
            'due' => $this->faker->dateTimeBetween('now', '+1 month'),
            'reference_number' => 'CHQ-'.$this->faker->unique()->numberBetween(1000, 9999),
        ];
    }
}
