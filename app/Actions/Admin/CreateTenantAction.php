<?php

namespace App\Actions\Admin;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DefaultRolesService;
use App\Services\SeedTenantDefaultsService;

class CreateTenantAction
{
    public function __construct(private DefaultRolesService $rolesService) {}

    public function handle(string $name, string $slug, User $owner): Tenant
    {
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => strtolower($slug),
            'is_active' => true,
        ]);

        $this->rolesService->seedForTenant($tenant);
        (new SeedTenantDefaultsService)->seedForTenant($tenant);

        $ownerRole = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'owner')
            ->firstOrFail();

        $tenant->users()->attach($owner->id, [
            'role' => 'owner',
            'role_id' => $ownerRole->id,
            'is_active' => true,
        ]);

        if (! $owner->current_tenant_id) {
            $owner->update(['current_tenant_id' => $tenant->id]);
        }

        return $tenant;
    }
}
