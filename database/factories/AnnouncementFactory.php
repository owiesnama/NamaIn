<?php

namespace Database\Factories;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_user_id' => User::factory(),
            'subject' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
            'audience_type' => AnnouncementAudience::All,
            'audience_meta' => null,
            'send_email' => false,
        ];
    }

    public function toTenant(int $tenantId): static
    {
        return $this->state([
            'audience_type' => AnnouncementAudience::Tenant,
            'audience_meta' => ['tenant_id' => $tenantId],
        ]);
    }

    public function toOwners(): static
    {
        return $this->state(['audience_type' => AnnouncementAudience::Owners]);
    }

    public function toTenantRole(int $tenantId, int $roleId): static
    {
        return $this->state([
            'audience_type' => AnnouncementAudience::TenantRole,
            'audience_meta' => ['tenant_id' => $tenantId, 'role_id' => $roleId],
        ]);
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function toUsers(array $userIds): static
    {
        return $this->state([
            'audience_type' => AnnouncementAudience::Users,
            'audience_meta' => ['user_ids' => $userIds],
        ]);
    }

    public function sent(): static
    {
        return $this->state(['sent_at' => now()]);
    }
}
