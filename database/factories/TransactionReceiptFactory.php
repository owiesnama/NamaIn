<?php

namespace Database\Factories;

use App\Models\Storage;
use App\Models\Transaction;
use App\Models\TransactionReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionReceipt>
 */
class TransactionReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'storage_id' => Storage::factory(),
            'received_by' => User::factory(),
            'quantity' => $this->faker->numberBetween(1, 50),
            'received_at' => $this->faker->dateTimeThisMonth(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
