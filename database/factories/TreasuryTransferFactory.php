<?php

namespace Database\Factories;

use App\Models\TreasuryAccount;
use App\Models\TreasuryTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreasuryTransfer>
 */
class TreasuryTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'from_account_id' => TreasuryAccount::factory(),
            'to_account_id' => TreasuryAccount::factory(),
            'created_by' => User::factory(),
            'amount' => $this->faker->numberBetween(1000, 100000),
            'notes' => null,
            'transferred_at' => now(),
        ];
    }
}
