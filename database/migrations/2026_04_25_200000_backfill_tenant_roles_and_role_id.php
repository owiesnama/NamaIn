<?php

use App\Models\Tenant;
use App\Services\DefaultRolesService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: seed default roles for every tenant and backfill role_id
 * on the tenant_user pivot using the existing legacy `role` string column.
 *
 * Legacy role values that predate RBAC → mapped to the closest default role:
 *   owner   → owner
 *   manager → manager
 *   cashier → cashier
 *   staff   → staff
 *   member  → staff   (old default before roles existed)
 *   admin   → owner   (very old alias used before multi-tenancy)
 *   *       → staff   (safe fallback for any unknown value)
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $legacyMap = [
        'owner' => 'owner',
        'admin' => 'owner',
        'manager' => 'manager',
        'cashier' => 'cashier',
        'staff' => 'staff',
        'member' => 'staff',
    ];

    public function up(): void
    {
        // 1. Ensure global permissions table is up-to-date.
        (new PermissionSeeder)->run();

        $service = new DefaultRolesService;

        // 2. Seed default roles for every tenant (idempotent via updateOrCreate).
        Tenant::each(function (Tenant $tenant) use ($service): void {
            $service->seedForTenant($tenant);
        });

        // 3. Backfill role_id for every tenant_user row that still has role_id = NULL.
        DB::table('tenant_user')
            ->whereNull('role_id')
            ->orderBy('tenant_id')
            ->lazyById(200, 'id')
            ->each(function (object $pivot): void {
                $targetSlug = $this->legacyMap[$pivot->role] ?? 'staff';

                $roleId = DB::table('roles')
                    ->where('tenant_id', $pivot->tenant_id)
                    ->where('slug', $targetSlug)
                    ->value('id');

                if ($roleId) {
                    DB::table('tenant_user')
                        ->where('id', $pivot->id)
                        ->update([
                            'role_id' => $roleId,
                            'role' => $targetSlug,   // normalise legacy values too
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Nothing to reverse for the role_id backfill — removing data would be destructive.
        // Roles and permissions have their own migrations to drop if needed.
    }
};
