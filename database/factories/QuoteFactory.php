<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'status' => QuoteStatus::Active,
            'expires_at' => $this->faker->optional()->dateTimeBetween('tomorrow', '+30 days'),
            'discount' => $this->faker->randomFloat(2, 0, 50),
            'notes' => $this->faker->optional()->sentence(),
            'currency' => 'SDG',
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => QuoteStatus::Active]);
    }

    public function converted(): static
    {
        return $this->state(['status' => QuoteStatus::Converted]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => QuoteStatus::Expired,
            'expires_at' => $this->faker->dateTimeBetween('-30 days', 'yesterday'),
        ]);
    }
}
