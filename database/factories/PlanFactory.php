<?php

namespace Database\Factories;

use App\Features\Feature;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'key' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'name' => ['en' => Str::title($name), 'ar' => Str::title($name)],
            'description' => ['en' => fake()->sentence(), 'ar' => fake()->sentence()],
            'is_active' => true,
            'is_default' => false,
            'sort' => 0,
            'price' => null,
            'currency' => null,
            'interval' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Attach a feature value after the plan is created.
     */
    public function withFeature(Feature $feature, bool|int|null $value): static
    {
        return $this->afterCreating(function (Plan $plan) use ($feature, $value) {
            $plan->planFeatures()->create([
                'feature_key' => $feature->value,
                'value' => $value,
            ]);
        });
    }
}
