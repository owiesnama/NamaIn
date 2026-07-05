<?php

namespace Database\Factories;

use App\Enums\RejectionReason;
use App\Models\Device;
use App\Models\ParkedMutation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ParkedMutation>
 */
class ParkedMutationFactory extends Factory
{
    protected $model = ParkedMutation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'device_id' => Device::factory(),
            'mutation_type' => 'sale.create',
            'idempotency_key' => (string) Str::ulid(),
            'rejection_reason' => RejectionReason::ValidationFailed,
            'rejection_message' => 'The mutation payload failed validation.',
            'envelope' => ['type' => 'sale.create', 'payload' => []],
            'occurred_at' => now(),
        ];
    }
}
