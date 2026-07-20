<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'starts_at' => now(),
            'ends_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);
    }

    public function trialing(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function expiredTrial(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Canceled,
            'ends_at' => now()->subDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Active,
            'ends_at' => now()->subDay(),
        ]);
    }
}
