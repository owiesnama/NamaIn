<?php

namespace Database\Factories;

use App\Enums\ReconciliationType;
use App\Models\ParkedMutation;
use App\Models\ReconciliationItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationItem>
 */
class ReconciliationItemFactory extends Factory
{
    protected $model = ReconciliationItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => app()->has('currentTenant') ? app('currentTenant')->id : Tenant::factory(),
            'type' => ReconciliationType::ParkedMutation,
            'subject_type' => (new ParkedMutation)->getMorphClass(),
            'subject_id' => ParkedMutation::factory(),
            'occurred_at' => now(),
            'detected_at' => now(),
            'status' => ReconciliationItem::STATUS_OPEN,
        ];
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => ReconciliationItem::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
    }
}
