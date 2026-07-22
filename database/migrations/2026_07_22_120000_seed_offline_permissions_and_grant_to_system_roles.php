<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The offline stack introduced reconciliation.* and devices.* permissions.
     * PermissionSeeder only runs on fresh installs (via the 2026_04_25 role
     * backfill), so existing deployments never receive them and the tenant
     * Devices/Reconciliation navigation stays hidden for everyone. Seed the
     * catalog up to date and grant the new slugs to the system roles that
     * DefaultRolesService gives them to (owner and manager). Custom roles are
     * untouched — tenants grant those through the roles UI.
     */
    private const NEW_SLUGS = [
        'reconciliation.view',
        'reconciliation.resolve',
        'devices.view',
        'devices.manage',
    ];

    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissionIds = Permission::whereIn('slug', self::NEW_SLUGS)->pluck('id')->all();

        Role::withoutGlobalScopes()
            ->whereIn('slug', ['owner', 'manager'])
            ->where('is_system', true)
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds));
    }

    public function down(): void {}
};
