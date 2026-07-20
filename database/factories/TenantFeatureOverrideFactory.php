<?php

namespace Database\Factories;

use App\Features\Feature;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantFeatureOverride>
 */
class TenantFeatureOverrideFactory extends Factory
{
    protected $model = TenantFeatureOverride::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'feature_key' => Feature::Bookings->value,
            'value' => true,
            'expires_at' => null,
        ];
    }

    public function forFeature(Feature $feature, bool|int|null $value): static
    {
        return $this->state([
            'feature_key' => $feature->value,
            'value' => $value,
        ]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function expiring(\DateTimeInterface $at): static
    {
        return $this->state(['expires_at' => $at]);
    }
}
