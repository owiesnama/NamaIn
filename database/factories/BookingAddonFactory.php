<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingAddon;
use App\Models\ServiceAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingAddonFactory extends Factory
{
    protected $model = BookingAddon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'service_addon_id' => ServiceAddon::factory(),
            'name' => $this->faker->randomElement([
                'كشف إضافي',
                'تقرير مفصّل',
                'خدمة عاجلة',
            ]),
            'price_delta' => $this->faker->numberBetween(10, 200),
        ];
    }
}
