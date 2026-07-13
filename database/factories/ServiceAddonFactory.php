<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ServiceAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceAddonFactory extends Factory
{
    protected $model = ServiceAddon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->service(),
            'name' => $this->faker->randomElement([
                'كشف إضافي',
                'تقرير مفصّل',
                'خدمة عاجلة',
                'متابعة منزلية',
            ]),
            'price_delta' => $this->faker->numberBetween(10, 200),
        ];
    }
}
