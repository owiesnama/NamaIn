<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'payment_method' => $this->faker->randomElement(PaymentMethod::cases()),
            'reference' => $this->faker->optional()->numerify('REF-#####'),
            'notes' => $this->faker->optional()->sentence(),
            'paid_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'created_by' => User::factory(),
        ];
    }
}
