<?php

use App\Services\DefaultRolesService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DefaultRolesService::roleNames() as $slug => $arabicName) {
            DB::table('roles')
                ->where('slug', $slug)
                ->update(['name' => $arabicName]);
        }
    }

    public function down(): void
    {
        $english = [
            'owner'   => 'Owner',
            'manager' => 'Manager',
            'cashier' => 'Cashier',
            'staff'   => 'Staff',
        ];

        foreach ($english as $slug => $name) {
            DB::table('roles')
                ->where('slug', $slug)
                ->update(['name' => $name]);
        }
    }
};
