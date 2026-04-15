<?php

namespace Database\Factories;

use App\Models\Storage;
use Illuminate\Database\Eloquent\Factories\Factory;

class StorageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Storage::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $storages = [
            'المستودع الرئيسي',
            'المستودع الثاني',
            'مركز توزيع الساحل الشرقي',
            'مرفق التخزين البارد',
            'ساحة المواد الخام',
            'مستودع البضائع الجاهزة',
            'المركز الإقليمي أ',
            'المركز الإقليمي ب',
            'مخزن الترانزيت',
            'معرض وسط المدينة',
            'مركز الخدمات اللوجستية الشمالي',
            'وحدة تخزين الجنوب',
            'نقطة توزيع الغرب',
            'مستودع بجانب الميناء',
            'محطة شحن المطار',
        ];

        return [
            'name' => $this->faker->randomElement($storages),
            'address' => $this->faker->address(),
        ];
    }
}
