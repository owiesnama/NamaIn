<?php

namespace Database\Seeders;

use App\Features\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlan('free', ['en' => 'Free', 'ar' => 'مجاني'], isDefault: true, sort: 0, features: [
            Feature::Bookings->value => false,
            Feature::Pos->value => true,
            Feature::MultiWarehouse->value => false,
            Feature::Quotes->value => false,
            Feature::AdvancedReports->value => false,
            Feature::Exports->value => false,
            Feature::Cheques->value => false,
            Feature::MaxProducts->value => 50,
            Feature::MaxUsers->value => 2,
            Feature::MaxWarehouses->value => 1,
        ]);

        $this->seedPlan('basic', ['en' => 'Basic', 'ar' => 'أساسي'], isDefault: false, sort: 1, features: [
            Feature::Bookings->value => true,
            Feature::Pos->value => true,
            Feature::MultiWarehouse->value => false,
            Feature::Quotes->value => true,
            Feature::AdvancedReports->value => false,
            Feature::Exports->value => true,
            Feature::Cheques->value => true,
            Feature::MaxProducts->value => 500,
            Feature::MaxUsers->value => 5,
            Feature::MaxWarehouses->value => 2,
        ]);

        $this->seedPlan('pro', ['en' => 'Pro', 'ar' => 'احترافي'], isDefault: false, sort: 2, features: [
            Feature::Bookings->value => true,
            Feature::Pos->value => true,
            Feature::MultiWarehouse->value => true,
            Feature::Quotes->value => true,
            Feature::AdvancedReports->value => true,
            Feature::Exports->value => true,
            Feature::Cheques->value => true,
            Feature::MaxProducts->value => null,      // unlimited
            Feature::MaxUsers->value => 25,
            Feature::MaxWarehouses->value => null,     // unlimited
        ]);
    }

    /**
     * @param  array<string, string>  $name
     * @param  array<string, bool|int|null>  $features
     */
    private function seedPlan(string $key, array $name, bool $isDefault, int $sort, array $features): void
    {
        $plan = Plan::updateOrCreate(
            ['key' => $key],
            ['name' => $name, 'is_active' => true, 'is_default' => $isDefault, 'sort' => $sort],
        );

        foreach ($features as $featureKey => $value) {
            $plan->planFeatures()->updateOrCreate(
                ['feature_key' => $featureKey],
                ['value' => $value],
            );
        }
    }
}
