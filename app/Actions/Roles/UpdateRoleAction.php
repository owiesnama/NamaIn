<?php

namespace App\Actions\Roles;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Validation\ValidationException;

class UpdateRoleAction
{
    /**
     * @param  int[]  $permissionIds
     */
    public function handle(Role $role, string $name, array $permissionIds): Role
    {
        if ($role->slug === 'owner') {
            throw ValidationException::withMessages([
                'role' => __('The owner role cannot be modified.'),
            ]);
        }

        // System roles (manager, cashier, staff) can have permissions updated but not renamed.
        if (! $role->is_system) {
            $role->update(['name' => $name]);
        }

        $role->permissions()->sync(
            Permission::whereIn('id', $permissionIds)->pluck('id')
        );

        return $role->fresh('permissions');
    }
}
