<?php

namespace Database\Factories;

use App\Enums\ExportStatus;
use App\Models\ExportLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportLog>
 */
class ExportLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'export_key' => $this->faker->randomElement(['sales-report', 'purchase-report', 'expenses', 'customers']),
            'format' => 'xlsx',
            'filters' => [],
            'status' => ExportStatus::Queued,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => ExportStatus::Completed,
            'path' => 'exports/test-export.xlsx',
            'filename' => 'test-export.xlsx',
            'expires_at' => now()->addDays(90),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => ExportStatus::Failed,
            'failure_message' => 'Export generation failed.',
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => ExportStatus::Processing,
        ]);
    }

    public function expired(): static
    {
        return $this->completed()->state([
            'expires_at' => now()->subDay(),
        ]);
    }
}
