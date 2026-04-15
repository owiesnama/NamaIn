<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $banks = [
            'بنك الخرطوم',
            'بنك فيصل الإسلامي',
            'بنك أم درمان الوطني',
            'بنك البركة',
            'مصرف السلام',
            'بنك بيبلوس',
            'بنك الثروة الحيوانية',
            'بنك التضامن الإسلامي',
            'بنك الساحل والصحراء',
            'بنك النيل',
        ];

        return [
            'name' => $this->faker->randomElement($banks),
            'code' => $this->faker->unique()->numerify('###'),
        ];
    }
}
