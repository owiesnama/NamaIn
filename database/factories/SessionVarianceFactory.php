<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\PosSession;
use App\Models\Register;
use App\Models\SessionVariance;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionVariance>
 */
class SessionVarianceFactory extends Factory
{
    protected $model = SessionVariance::class;

    public function definition(): array
    {
        $expected = fake()->numberBetween(10000, 500000);
        $declared = $expected + fake()->numberBetween(-5000, 5000);
        $tenantId = app()->has('currentTenant') ? app('currentTenant')->id : Tenant::factory();

        return [
            'tenant_id' => $tenantId,
            'device_id' => Device::factory()->state(['tenant_id' => $tenantId]),
            'register_id' => Register::factory()->state(['tenant_id' => $tenantId]),
            'pos_session_id' => PosSession::factory()->state(['tenant_id' => $tenantId]),
            'treasury_account_id' => TreasuryAccount::factory()->state(['tenant_id' => $tenantId]),
            'expected_amount' => $expected,
            'declared_amount' => $declared,
            'variance_amount' => $declared - $expected,
            'occurred_at' => now(),
        ];
    }
}
