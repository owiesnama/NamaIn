<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;

class DefaultRolesService
{
    /** @return array<string, string[]> */
    public static function rolePermissions(): array
    {
        $allSlugs = array_keys(array_merge(...array_values(PermissionSeeder::permissions())));

        return [
            'owner' => $allSlugs,
            'manager' => array_values(array_filter($allSlugs, fn ($s) => ! in_array($s, [
                'users.assign-role',
                'roles.manage',
            ]))),
            'cashier' => [
                'products.view',
                'customers.view',
                'suppliers.view',
                'sales.view',
                'sales.create',
                'pos.view',
                'pos.operate',
                'pos.manage-sessions',
                'payments.view',
                'payments.create',
                'inventory.view',
            ],
            'staff' => [
                'products.view',
                'customers.view',
                'suppliers.view',
                'sales.view',
                'purchases.view',
                'inventory.view',
                'expenses.view',
                'payments.view',
                'treasury.view',
            ],
        ];
    }

    public function seedForTenant(Tenant $tenant): void
    {
        $permissionMap = Permission::all()->keyBy('slug');

        foreach (self::rolePermissions() as $slug => $permissionSlugs) {
            $role = Role::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                [
                    'name' => ucfirst($slug),
                    'is_system' => true,
                ]
            );

            $permissionIds = collect($permissionSlugs)
                ->map(fn ($s) => $permissionMap->get($s)?->id)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
