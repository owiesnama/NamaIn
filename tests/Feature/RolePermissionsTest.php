<?php

use App\Models\Adjustment;
use App\Models\Invoice;
use App\Models\PosSession;
use App\Models\StockTransfer;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;

test('user can get role in current tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'manager']);

    expect($user->roleInCurrentTenant())->toBe('manager');
});

test('user can check if it has a specific role', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user, ['role' => 'cashier']);

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
    $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($owner, ['role' => 'owner']);

    $cashier = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($cashier, ['role' => 'cashier']);

    $staff = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($staff, ['role' => 'staff']);

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
