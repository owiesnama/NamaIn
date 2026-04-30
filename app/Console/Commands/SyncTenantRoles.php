<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\DefaultRolesService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;

class SyncTenantRoles extends Command
{
    protected $signature = 'tenants:sync-roles';

    protected $description = 'Re-seed permissions and sync default roles for all tenants';

    public function handle(DefaultRolesService $service): int
    {
        (new PermissionSeeder)->run();

        $tenants = Tenant::withoutGlobalScopes()->get();

        foreach ($tenants as $tenant) {
            $service->seedForTenant($tenant);
        }

        $this->info("Synced roles for {$tenants->count()} tenant(s).");

        return self::SUCCESS;
    }
}
