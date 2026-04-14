<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'total' => $this->faker->randomFloat(2, 100, 10000),
            'payment_method' => $this->faker->randomElement(PaymentMethod::cases()),
            'payment_status' => $this->faker->randomElement(PaymentStatus::cases()),
            'paid_amount' => $this->faker->randomFloat(2, 0, 100),
            'discount' => $this->faker->randomFloat(2, 0, 50),
            'invocable_id' => Customer::factory(),
            'invocable_type' => Customer::class,
            'serial_number' => $this->faker->unique()->numerify('INV-#####'),
            'currency' => 'USD',
            'status' => $this->faker->randomElement(InvoiceStatus::cases()),
        ];
    }
}
