<?php

namespace Database\Factories;

use App\Enums\TreasuryAccountType;
use App\Models\TreasuryAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreasuryAccount>
 */
class TreasuryAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(TreasuryAccountType::cases()),
            'opening_balance' => 0,
            'currency' => 'SDG',
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function cash(): static
    {
        return $this->state(['type' => TreasuryAccountType::Cash]);
    }

    public function bank(): static
    {
        return $this->state(['type' => TreasuryAccountType::Bank]);
    }

    public function mobileMoney(): static
    {
        return $this->state(['type' => TreasuryAccountType::MobileMoney]);
    }

    public function chequeClearing(): static
    {
        return $this->state(['type' => TreasuryAccountType::ChequeClearing]);
    }

    public function withOpeningBalance(int $amount): static
    {
        return $this->state(['opening_balance' => $amount]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
