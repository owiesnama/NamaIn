<?php

use App\Enums\DeviceStatus;
use App\Enums\TreasuryAccountType;
use App\Models\Device;
use App\Models\Register;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\Services\OnBoarding\SeedTenantDefaultsService;
use Illuminate\Database\UniqueConstraintViolationException;

it('provisions exactly one reserved cloud register when a tenant is created', function () {
    $tenant = Tenant::create(['name' => 'Fresh Co', 'slug' => 'fresh-'.uniqid(), 'is_active' => true]);

    $registers = Register::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();

    expect($registers)->toHaveCount(1);
    expect($registers->first()->code)->toBe('R0');
    expect($registers->first()->isCloud())->toBeTrue();
    expect($registers->first()->storage_id)->toBeNull();
    expect(strlen($registers->first()->public_id))->toBe(26);
});

it('returns the same cloud register on repeated resolution', function () {
    $tenant = Tenant::create(['name' => 'Idem Co', 'slug' => 'idem-'.uniqid(), 'is_active' => true]);

    $first = Register::cloudRegisterFor($tenant);
    $second = Register::cloudRegisterFor($tenant);

    expect($second->id)->toBe($first->id);
    expect(Register::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('enforces a unique register code per tenant', function () {
    $tenant = Tenant::create(['name' => 'Dup Co', 'slug' => 'dup-'.uniqid(), 'is_active' => true]);

    Register::create(['tenant_id' => $tenant->id, 'code' => 'R0', 'is_cloud' => false]);
})->throws(UniqueConstraintViolationException::class);

it('casts device status and resolves its register', function () {
    $tenant = Tenant::create(['name' => 'Dev Co', 'slug' => 'dev-'.uniqid(), 'is_active' => true]);
    app()->instance('currentTenant', $tenant);
    $register = Register::cloudRegisterFor($tenant);

    $device = Device::create([
        'tenant_id' => $tenant->id,
        'register_id' => $register->id,
        'name' => 'Front counter',
    ]);

    expect($device->status)->toBe(DeviceStatus::Pending);
    expect($device->register->is($register))->toBeTrue();
    expect(strlen($device->public_id))->toBe(26);
});

it('wires the seeded pos cash drawer to the cloud register during onboarding', function () {
    $tenant = Tenant::create(['name' => 'Onboard Co', 'slug' => 'onboard-'.uniqid(), 'is_active' => true]);
    app()->instance('currentTenant', $tenant);

    (new SeedTenantDefaultsService)->seedForTenant($tenant);

    $cloud = Register::cloudRegisterFor($tenant);
    $posDrawer = TreasuryAccount::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->whereNotNull('sale_point_id')
        ->where('type', TreasuryAccountType::Cash)
        ->first();

    expect($posDrawer)->not->toBeNull();
    expect($posDrawer->register_id)->toBe($cloud->id);
});
