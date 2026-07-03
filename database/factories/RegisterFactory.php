<?php

namespace Database\Factories;

use App\Models\Register;
use App\Models\Storage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Register>
 */
class RegisterFactory extends Factory
{
    protected $model = Register::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'storage_id' => Storage::factory(),
            'code' => 'R'.fake()->unique()->numberBetween(1, 9999),
            'label' => fake()->words(2, true),
            'is_cloud' => false,
            'is_active' => true,
        ];
    }

    public function cloud(): static
    {
        return $this->state([
            'storage_id' => null,
            'code' => Register::CLOUD_CODE,
            'label' => 'Cloud',
            'is_cloud' => true,
        ]);
    }
}
