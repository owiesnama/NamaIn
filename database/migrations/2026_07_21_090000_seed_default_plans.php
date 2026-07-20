<?php

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Ensure the starter plans (incl. the default free plan) exist in every
     * deployed environment. Without a governing plan the entitlement resolver
     * runs in permissive mode and nothing is gated.
     *
     * Skipped in tests (each test arranges its own plans) and only seeds when
     * no plans exist yet, so it never clobbers admin-configured plans.
     */
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (Plan::count() === 0) {
            (new PlanSeeder)->run();
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: keep plans on rollback.
    }
};
