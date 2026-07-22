<?php

use App\Models\Permission;
use App\Models\Role;

/**
 * The 2026_07_22_120000 migration retrofits the offline permissions onto
 * deployments whose roles were seeded before the offline stack existed. The
 * suite's fresh database already has them, so each test first strips the new
 * slugs to reproduce the pre-migration production state.
 */
$offlineSlugs = ['reconciliation.view', 'reconciliation.resolve', 'devices.view', 'devices.manage'];

function runOfflinePermissionsBackfill(): void
{
    $migration = require database_path('migrations/2026_07_22_120000_seed_offline_permissions_and_grant_to_system_roles.php');
    $migration->up();
}

function stripOfflinePermissions(array $slugs): void
{
    $ids = Permission::whereIn('slug', $slugs)->pluck('id');

    Role::withoutGlobalScopes()->each(fn (Role $role) => $role->permissions()->detach($ids));
    Permission::whereIn('slug', $slugs)->delete();
}

it('seeds the offline permissions and grants them to owner and manager system roles', function () use ($offlineSlugs) {
    seedTenantRoles(app('currentTenant'));
    stripOfflinePermissions($offlineSlugs);

    expect(Permission::whereIn('slug', $offlineSlugs)->count())->toBe(0);

    runOfflinePermissionsBackfill();

    expect(Permission::whereIn('slug', $offlineSlugs)->count())->toBe(4);

    foreach (['owner', 'manager'] as $slug) {
        $role = Role::withoutGlobalScopes()->where('slug', $slug)->where('is_system', true)->first();
        expect($role->permissions->pluck('slug')->intersect($offlineSlugs)->count())
            ->toBe(4, "system role {$slug} should hold all offline permissions");
    }
});

it('does not grant the offline permissions to cashier, staff or custom roles', function () use ($offlineSlugs) {
    seedTenantRoles(app('currentTenant'));
    $custom = Role::create(['tenant_id' => app('currentTenant')->id, 'name' => 'Auditor', 'slug' => 'auditor', 'is_system' => false]);
    stripOfflinePermissions($offlineSlugs);

    runOfflinePermissionsBackfill();

    foreach (['cashier', 'staff'] as $slug) {
        $role = Role::withoutGlobalScopes()->where('slug', $slug)->where('is_system', true)->first();
        expect($role->permissions->pluck('slug')->intersect($offlineSlugs))->toBeEmpty();
    }

    expect($custom->fresh()->permissions->pluck('slug')->intersect($offlineSlugs))->toBeEmpty();
});

it('keeps existing role permissions intact and stays idempotent', function () use ($offlineSlugs) {
    seedTenantRoles(app('currentTenant'));
    stripOfflinePermissions($offlineSlugs);

    $owner = Role::withoutGlobalScopes()->where('slug', 'owner')->where('is_system', true)->first();
    $before = $owner->permissions->pluck('slug')->sort()->values();

    runOfflinePermissionsBackfill();
    runOfflinePermissionsBackfill();

    $after = $owner->fresh()->permissions->pluck('slug')->sort()->values();

    expect($after->intersect($before)->count())->toBe($before->count());
    expect($after->duplicates())->toBeEmpty();
});
