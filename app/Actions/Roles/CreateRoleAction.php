<?php

namespace App\Actions\Roles;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;

class CreateRoleAction
{
    /**
     * @param  int[]  $permissionIds
     */
    public function handle(Tenant $tenant, string $name, string $slug, array $permissionIds): Role
    {
        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => $slug,
            'is_system' => false,
        ]);

        $role->permissions()->sync(
            Permission::whereIn('id', $permissionIds)->pluck('id')
        );

        return $role;
    }
}
