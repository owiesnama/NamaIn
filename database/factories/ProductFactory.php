<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $products = [
            'لابتوب برو X1',
            'ماوس لاسلكي M20',
            'لوحة مفاتيح ميكانيكية K85',
            'شاشة LED 27 بوصة',
            'مكتب عمل XL',
            'كرسي مريح',
            'هاتف ذكي 5G',
            'سماعات بلوتوث',
            'قرص صلب خارجي 1 تيرابايت',
            'طابعة ليزر',
            'مولد صناعي',
            'مضخة هيدروليكية',
            'خوذة سلامة',
            'عوارض فولاذية 6 متر',
            'كيس أسمنت 50 كجم',
            'مثقاب كهربائي',
            'منشار كهربائي',
            'لوح شمسي 400 واط',
            'حزمة بطاريات 10 أمبير',
            'سلك نحاسي 100 متر',
            'محلول تنظيف 5 لتر',
            'كمامات طبية (علبة 50 قطعة)',
            'معقم يدين 1 لتر',
            'حقيبة إسعافات أولية',
            'حبوب قهوة 1 كجم',
            'صندوق مياه معبأة',
            'راوتر AC1900',
            'موزع شبكة 24 منفذ',
            'جهاز عرض HD',
            'جهاز لوحي 10 بوصة',
            'ماكينة لحام',
            'إطار رافعة شوكية',
            'سير ناقل 10 متر',
            'ضاغط هواء',
            'نظارات سلامة',
            'قفازات عمل (زوج)',
            'سترة عاكسة',
            'كشاف LED',
            'مقياس رقمي متعدد',
            'كاوية لحام',
        ];

        $cost = $this->faker->numberBetween(400, 10000);

        return [
            'name' => $this->faker->randomElement($products),
            'cost' => $cost,
            'price' => (int) round($cost * 1.2),
            'average_cost' => $cost,
            'expire_date' => $this->faker->dateTimeBetween('+1 month', '+2 years'),
        ];
    }

    /**
     * A physical good — explicit equivalent of the default state.
     */
    public function physical(): static
    {
        return $this->state(fn () => ['type' => ProductType::Physical->value]);
    }

    /**
     * A bookable service: no stock/expiry, a positive duration, priced by its
     * base price. Defaults to a non-on-site, non-overlapping, bookable service.
     */
    public function service(): static
    {
        return $this->state(fn () => [
            'type' => ProductType::Service->value,
            'cost' => 0,
            'price' => $this->faker->numberBetween(50, 500),
            'average_cost' => 0,
            'expire_date' => null,
            'duration_minutes' => $this->faker->randomElement([30, 45, 60, 90]),
            'requires_booking' => true,
            'on_site' => false,
            'allow_overlap' => false,
            'travel_buffer_minutes' => null,
        ]);
    }

    /**
     * An on-site service that requires travel (address + travel buffer apply).
     */
    public function onSite(int $bufferMinutes = 30): static
    {
        return $this->state(fn () => [
            'on_site' => true,
            'travel_buffer_minutes' => $bufferMinutes,
        ]);
    }

    /**
     * A service that permits overlapping (double-booked) appointments.
     */
    public function allowOverlap(): static
    {
        return $this->state(fn () => ['allow_overlap' => true]);
    }
}
