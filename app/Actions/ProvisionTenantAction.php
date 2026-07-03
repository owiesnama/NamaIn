<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnBoarding\DefaultRolesService;
use App\Services\OnBoarding\SeedTenantDefaultsService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionTenantAction
{
    public function __construct(
        private PermissionSeeder $permissionSeeder,
        private DefaultRolesService $rolesService,
        private SeedTenantDefaultsService $defaultsService,
    ) {}

    public function handle(string $name, string $slug, User $owner): Tenant
    {
        return DB::transaction(function () use ($name, $slug, $owner) {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => Str::lower($slug),
                'is_active' => true,
            ]);

            $this->permissionSeeder->run();
            $this->rolesService->seedForTenant($tenant);
            $this->defaultsService->seedForTenant($tenant);

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
        });
    }
}
