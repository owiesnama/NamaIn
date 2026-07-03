<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Register;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'register_id' => Register::factory(),
            'name' => fake()->words(2, true).' iPad',
            'status' => DeviceStatus::Pending,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => DeviceStatus::Active,
            'provisioned_at' => now(),
        ]);
    }
}
