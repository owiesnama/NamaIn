<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('+1 hour', '+2 weeks');

        return [
            'service_product_id' => Product::factory()->service(),
            'customer_id' => Customer::factory(),
            'starts_at' => $startsAt,
            'status' => BookingStatus::Confirmed,
            'address' => null,
            'notes' => null,
            'base_price' => $this->faker->numberBetween(50, 500),
            'total' => 0,
            // ends_at is derived from the service duration by the model's saving hook.
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Cancelled]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Completed]);
    }

    /**
     * Anchor the booking to a specific start instant.
     */
    public function startingAt(\DateTimeInterface|string $startsAt): static
    {
        return $this->state(fn () => ['starts_at' => $startsAt]);
    }
}
