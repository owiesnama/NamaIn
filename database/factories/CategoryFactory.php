<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'إلكترونيات',
            'أدوات مكتبية',
            'معدات صناعية',
            'مواد بناء',
            'مستلزمات طبية',
            'منسوجات',
            'أجهزة ومعدات',
            'قطع غيار سيارات',
            'مواد كيميائية',
            'أغذية ومشروبات',
            'معدات سلامة',
            'أدوات يدوية',
            'أثاث',
            'أدوات تنظيف',
            'مواد تغليف',
            'مكونات كهربائية',
            'مستلزمات سباكة',
            'معدات مختبرات',
            'البنية التحتية لتكنولوجيا المعلومات',
            'قطع غيار آلات',
        ];

        return [
            'name' => $this->faker->randomElement($categories),
        ];
    }
}
