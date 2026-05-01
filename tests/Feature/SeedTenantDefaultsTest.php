<?php

use App\Enums\StorageType;
use App\Enums\TreasuryAccountType;
use App\Models\Customer;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\Services\SeedTenantDefaultsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeding creates a warehouse', function () {
    $tenant = Tenant::factory()->create();

    (new SeedTenantDefaultsService)->seedForTenant($tenant);

    expect(Storage::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', StorageType::WAREHOUSE)->count())->toBe(1);
});

test('seeding creates a sale point', function () {
    $tenant = Tenant::factory()->create();

    (new SeedTenantDefaultsService)->seedForTenant($tenant);

    expect(Storage::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', StorageType::SALE_POINT)->count())->toBe(1);
});

test('seeding creates a cash drawer linked to the sale point', function () {
    $tenant = Tenant::factory()->create();

    (new SeedTenantDefaultsService)->seedForTenant($tenant);

    $pos = Storage::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', StorageType::SALE_POINT)->first();

    expect(TreasuryAccount::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('type', TreasuryAccountType::Cash)
        ->where('sale_point_id', $pos->id)
        ->count()
    )->toBe(1);
});

test('seeding creates a shared cash account', function () {
    $tenant = Tenant::factory()->create();

    (new SeedTenantDefaultsService)->seedForTenant($tenant);

    expect(TreasuryAccount::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('type', TreasuryAccountType::Cash)
        ->whereNull('sale_point_id')
        ->count()
    )->toBe(1);
});

test('seeding creates a cheque clearing account', function () {
    $tenant = Tenant::factory()->create();

    (new SeedTenantDefaultsService)->seedForTenant($tenant);

    expect(TreasuryAccount::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('type', TreasuryAccountType::ChequeClearing)
        ->count()
    )->toBe(1);
});

test('seeding creates a walk-in customer', function () {
    $tenant = Tenant::factory()->create();

    (new SeedTenantDefaultsService)->seedForTenant($tenant);

    expect(Customer::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('is_system', true)
        ->count()
    )->toBe(1);
});
