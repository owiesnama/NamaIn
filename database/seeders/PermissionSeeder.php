<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /** @return array<string, array<string, string>> */
    public static function permissions(): array
    {
        return [
            'users' => [
                'users.view' => 'View team members',
                'users.invite' => 'Invite new members',
                'users.manage' => 'Enable/disable and remove members',
                'users.assign-role' => 'Assign roles to members',
            ],
            'roles' => [
                'roles.manage' => 'Create, edit, and delete roles',
            ],
            'products' => [
                'products.view' => 'View products',
                'products.create' => 'Create products',
                'products.update' => 'Update products',
                'products.delete' => 'Delete products',
            ],
            'customers' => [
                'customers.view' => 'View customers',
                'customers.create' => 'Create customers',
                'customers.update' => 'Update customers',
                'customers.delete' => 'Delete customers',
            ],
            'suppliers' => [
                'suppliers.view' => 'View suppliers',
                'suppliers.create' => 'Create suppliers',
                'suppliers.update' => 'Update suppliers',
                'suppliers.delete' => 'Delete suppliers',
            ],
            'sales' => [
                'sales.view' => 'View sales invoices',
                'sales.create' => 'Create sales invoices',
                'sales.return' => 'Process sales returns',
                'sales.delete' => 'Delete sales invoices',
            ],
            'purchases' => [
                'purchases.view' => 'View purchase invoices',
                'purchases.create' => 'Create purchase invoices',
                'purchases.return' => 'Process purchase returns',
                'purchases.delete' => 'Delete purchase invoices',
            ],
            'inventory' => [
                'inventory.view' => 'View inventory and stock',
                'inventory.manage' => 'Manage stock adjustments',
                'inventory.transfer' => 'Transfer stock between warehouses',
            ],
            'pos' => [
                'pos.view' => 'View POS',
                'pos.operate' => 'Operate POS checkout',
                'pos.manage-sessions' => 'Open and close POS sessions',
            ],
            'expenses' => [
                'expenses.view' => 'View expenses',
                'expenses.create' => 'Create expenses',
                'expenses.delete' => 'Delete expenses',
            ],
            'payments' => [
                'payments.view' => 'View payments',
                'payments.create' => 'Record payments',
                'payments.manage-cheques' => 'Manage cheques',
            ],
            'treasury' => [
                'treasury.view' => 'View treasury accounts',
                'treasury.create' => 'Create treasury accounts',
                'treasury.transfer' => 'Transfer between accounts',
                'treasury.adjust' => 'Adjust treasury balances',
            ],
            'settings' => [
                'settings.view' => 'View settings',
                'settings.update' => 'Update settings',
            ],
            'reports' => [
                'reports.view' => 'View reports',
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::permissions() as $group => $permissions) {
            foreach ($permissions as $slug => $description) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['group' => $group, 'description' => $description]
                );
            }
        }
    }
}
