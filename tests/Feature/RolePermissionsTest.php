<?php

use App\Models\Adjustment;
use App\Models\Invoice;
use App\Models\PosSession;
use App\Models\Role;
use App\Models\StockTransfer;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;

test('user can get role in current tenant', function () {
    $tenant = Tenant::factory()->create();
    seedTenantRoles($tenant);
    $managerRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'manager')->first();

    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'manager', 'role_id' => $managerRole->id, 'is_active' => true]);

    expect($user->roleInCurrentTenant()?->slug)->toBe('manager');
});

test('user can check if it has a specific role', function () {
    $tenant = Tenant::factory()->create();
    seedTenantRoles($tenant);
    $cashierRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'cashier')->first();

    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'cashier', 'role_id' => $cashierRole->id, 'is_active' => true]);

    expect($user->hasRole('cashier'))->toBeTrue();
    expect($user->hasRole('owner'))->toBeFalse();
    expect($user->hasRole('cashier', 'manager'))->toBeTrue();
});

test('user without current tenant returns null for roleInCurrentTenant', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);
    expect($user->roleInCurrentTenant())->toBeNull();
});

test('policies enforce roles correctly', function () {
    $tenant = Tenant::factory()->create();
    seedTenantRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'owner')->first();
    $cashierRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'cashier')->first();
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'staff')->first();

    $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    $cashier = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($cashier, ['role' => 'cashier', 'role_id' => $cashierRole->id, 'is_active' => true]);

    $staff = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($staff, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    // StoragePolicy
    expect($owner->can('create', Storage::class))->toBeTrue();
    expect($cashier->can('create', Storage::class))->toBeFalse();
    expect($cashier->can('viewAny', Storage::class))->toBeTrue();

    // StockTransferPolicy
    expect($owner->can('create', StockTransfer::class))->toBeTrue();
    expect($cashier->can('create', StockTransfer::class))->toBeFalse();

    // AdjustmentPolicy
    expect($owner->can('create', Adjustment::class))->toBeTrue();
    expect($cashier->can('create', Adjustment::class))->toBeFalse();

    // PosSessionPolicy
    expect($owner->can('create', PosSession::class))->toBeTrue();
    expect($cashier->can('create', PosSession::class))->toBeTrue();
    expect($staff->can('create', PosSession::class))->toBeFalse();

    // InvoicePolicy
    expect($cashier->can('create', Invoice::class))->toBeTrue();
    expect($staff->can('create', Invoice::class))->toBeFalse();
});

// ────────────────────────────────────────────────────────
// Role model
// ────────────────────────────────────────────────────────

test('Role hasPermission returns true when permission is assigned', function () {
    $tenant = Tenant::factory()->create();
    seedTenantRoles($tenant);

    $managerRole = Role::withoutGlobalScopes()
        ->with('permissions')
        ->where('tenant_id', $tenant->id)
        ->where('slug', 'manager')
        ->first();

    expect($managerRole->hasPermission('products.view'))->toBeTrue();
});

test('Role hasPermission returns false for a permission not assigned', function () {
    $tenant = Tenant::factory()->create();
    seedTenantRoles($tenant);

    $staffRole = Role::withoutGlobalScopes()
        ->with('permissions')
        ->where('tenant_id', $tenant->id)
        ->where('slug', 'staff')
        ->first();

    // Staff cannot manage roles
    expect($staffRole->hasPermission('roles.manage'))->toBeFalse();
});

test('owner role has all permissions', function () {
    $tenant = Tenant::factory()->create();
    seedTenantRoles($tenant);

    $ownerRole = Role::withoutGlobalScopes()
        ->with('permissions')
        ->where('tenant_id', $tenant->id)
        ->where('slug', 'owner')
        ->first();

    expect($ownerRole->hasPermission('roles.manage'))->toBeTrue();
    expect($ownerRole->hasPermission('inventory.manage'))->toBeTrue();
    expect($ownerRole->hasPermission('treasury.view'))->toBeTrue();
});

// ────────────────────────────────────────────────────────
// User model: isActiveInTenant
// ────────────────────────────────────────────────────────

test('isActiveInTenant returns true for an active member', function () {
    $tenant = Tenant::factory()->create();
    seedTenantRoles($tenant);
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'staff')->first();

    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    expect($user->isActiveInTenant($tenant))->toBeTrue();
});

test('isActiveInTenant returns false for a disabled member', function () {
    $tenant = Tenant::factory()->create();
    seedTenantRoles($tenant);
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'staff')->first();

    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => false]);

    expect($user->isActiveInTenant($tenant))->toBeFalse();
});

test('isActiveInTenant returns false for a user not in the tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    expect($user->isActiveInTenant($tenant))->toBeFalse();
});

test('User hasPermission uses role permissions correctly', function () {
    $tenant = Tenant::factory()->create();
    seedTenantRoles($tenant);
    app()->instance('currentTenant', $tenant);

    $cashierRole = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'cashier')->first();
    $cashier = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($cashier, ['role' => 'cashier', 'role_id' => $cashierRole->id, 'is_active' => true]);

    expect($cashier->hasPermission('pos.operate'))->toBeTrue();
    expect($cashier->hasPermission('roles.manage'))->toBeFalse();
    expect($cashier->hasPermission('inventory.manage'))->toBeFalse();
});

test('User hasPermission returns false when user has no role', function () {
    $user = User::factory()->create(['current_tenant_id' => null]);

    expect($user->hasPermission('products.view'))->toBeFalse();
});
