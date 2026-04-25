<?php

namespace Database\Factories;

use App\Enums\CustomerAdvanceStatus;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAdvance>
 */
class CustomerAdvanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'treasury_account_id' => TreasuryAccount::factory(),
            'created_by' => User::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'settled_amount' => 0,
            'status' => CustomerAdvanceStatus::Outstanding,
            'notes' => $this->faker->optional()->sentence(),
            'given_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function outstanding(): static
    {
        return $this->state([
            'settled_amount' => 0,
            'status' => CustomerAdvanceStatus::Outstanding,
        ]);
    }

    public function partiallySettled(): static
    {
        return $this->state(function (array $attributes) {
            $amount = (float) $attributes['amount'];
            $settled = round($amount * 0.5, 2);

            return [
                'settled_amount' => $settled,
                'status' => CustomerAdvanceStatus::PartiallySettled,
            ];
        });
    }

    public function settled(): static
    {
        return $this->state([
            'status' => CustomerAdvanceStatus::Settled,
        ])->afterMaking(function (CustomerAdvance $advance) {
            $advance->settled_amount = $advance->amount;
        })->afterCreating(function (CustomerAdvance $advance) {
            $advance->settled_amount = $advance->amount;
            $advance->save();
        });
    }
}
