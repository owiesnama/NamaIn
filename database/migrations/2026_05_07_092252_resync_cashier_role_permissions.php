<?php

use App\Models\Permission;
use App\Models\Role;
use App\Services\OnBoarding\DefaultRolesService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissionMap = Permission::all()->keyBy('slug');
        $cashierSlugs = DefaultRolesService::rolePermissions()['cashier'];

        $permissionIds = collect($cashierSlugs)
            ->map(fn ($s) => $permissionMap->get($s)?->id)
            ->filter()
            ->values()
            ->all();

        Role::withoutGlobalScopes()
            ->where('slug', 'cashier')
            ->where('is_system', true)
            ->each(fn (Role $role) => $role->permissions()->sync($permissionIds));
    }

    public function down(): void {}
};
