<?php

namespace Database\Factories;

use App\Enums\TreasuryMovementReason;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreasuryMovement>
 */
class TreasuryMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'treasury_account_id' => TreasuryAccount::factory(),
            'created_by' => User::factory(),
            'reason' => $this->faker->randomElement(TreasuryMovementReason::cases()),
            'amount' => $this->faker->numberBetween(-100000, 100000),
            'occurred_at' => $this->faker->dateTimeThisMonth(),
        ];
    }

    public function credit(?int $amount = null): static
    {
        return $this->state([
            'amount' => $amount ?? $this->faker->numberBetween(1000, 100000),
            'reason' => TreasuryMovementReason::PaymentReceived,
        ]);
    }

    public function debit(?int $amount = null): static
    {
        return $this->state([
            'amount' => -abs($amount ?? $this->faker->numberBetween(1000, 100000)),
            'reason' => TreasuryMovementReason::ExpensePaid,
        ]);
    }
}
