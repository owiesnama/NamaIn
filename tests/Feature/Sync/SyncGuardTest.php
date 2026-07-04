<?php

use App\Enums\DeviceStatus;
use App\Enums\StorageType;
use App\Http\Middleware\Sync\BindDeviceTenant;
use App\Http\Middleware\Sync\EnsureDeviceActive;
use App\Models\Device;
use App\Models\Product;
use App\Models\Register;
use App\Models\Storage;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

/**
 * Register a probe route wearing the full sync middleware stack so the guard
 * chain can be exercised before any real authenticated endpoint exists.
 */
beforeEach(function () {
    Route::middleware(['api', 'auth:sync', EnsureDeviceActive::class, BindDeviceTenant::class])
        ->get('/api/sync/v1/_probe', function () {
            return response()->json([
                'device' => auth('sync')->user()->public_id,
                'tenant' => app('currentTenant')->slug,
                'products' => Product::pluck('name'),
            ]);
        });
});

function activeDeviceFor(Tenant $tenant): Device
{
    app()->instance('currentTenant', $tenant);

    $storage = Storage::create([
        'name' => 'Store '.$tenant->slug,
        'address' => 'x',
        'type' => StorageType::SALE_POINT,
        'tenant_id' => $tenant->id,
    ]);

    $register = Register::create([
        'tenant_id' => $tenant->id,
        'storage_id' => $storage->id,
        'code' => 'R1',
    ]);

    return Device::create([
        'tenant_id' => $tenant->id,
        'register_id' => $register->id,
        'name' => 'Device '.$tenant->slug,
        'status' => DeviceStatus::Active,
        'provisioned_at' => now(),
    ]);
}

it('authenticates an active device token', function () {
    $tenant = app('currentTenant');
    $device = activeDeviceFor($tenant);
    $token = $device->createToken('sync', Device::TOKEN_ABILITIES)->plainTextToken;

    $this->getJson('/api/sync/v1/_probe', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('device', $device->public_id)
        ->assertJsonPath('tenant', $tenant->slug);
});

it('rejects requests without a token', function () {
    $this->getJson('/api/sync/v1/_probe')->assertUnauthorized();
});

it('rejects a revoked device with 403 device_revoked', function () {
    $device = activeDeviceFor(app('currentTenant'));
    $token = $device->createToken('sync', Device::TOKEN_ABILITIES)->plainTextToken;
    $device->update(['status' => DeviceStatus::Revoked]);

    $this->getJson('/api/sync/v1/_probe', ['Authorization' => "Bearer {$token}"])
        ->assertForbidden()
        ->assertJsonPath('error', 'device_revoked');
});

it('rejects a pending device with 401', function () {
    $device = activeDeviceFor(app('currentTenant'));
    $token = $device->createToken('sync', Device::TOKEN_ABILITIES)->plainTextToken;
    $device->update(['status' => DeviceStatus::Pending]);

    $this->getJson('/api/sync/v1/_probe', ['Authorization' => "Bearer {$token}"])
        ->assertUnauthorized();
});

it('rejects a deleted token immediately', function () {
    $device = activeDeviceFor(app('currentTenant'));
    $token = $device->createToken('sync', Device::TOKEN_ABILITIES)->plainTextToken;
    $device->tokens()->delete();

    $this->getJson('/api/sync/v1/_probe', ['Authorization' => "Bearer {$token}"])
        ->assertUnauthorized();
});

it('scopes every query to the device tenant, not the caller host', function () {
    $tenantA = app('currentTenant');
    Product::create(['name' => 'A-product', 'cost' => 1, 'tenant_id' => $tenantA->id]);

    $tenantB = Tenant::create(['name' => 'B Co', 'slug' => 'b-'.uniqid(), 'is_active' => true]);
    Product::withoutGlobalScopes()->create(['name' => 'B-product', 'cost' => 1, 'tenant_id' => $tenantB->id]);

    $deviceB = activeDeviceFor($tenantB);
    $tokenB = $deviceB->createToken('sync', Device::TOKEN_ABILITIES)->plainTextToken;

    // reset the container binding set during arrangement; middleware must rebind
    app()->forgetInstance('currentTenant');
    app()->instance('currentTenant', $tenantA);

    $this->getJson('/api/sync/v1/_probe', ['Authorization' => "Bearer {$tokenB}"])
        ->assertOk()
        ->assertJsonPath('tenant', $tenantB->slug)
        ->assertJsonPath('products', ['B-product']);
});

it('does not let a user token authenticate the sync guard', function () {
    $this->signIn();
    $userToken = auth()->user()->createToken('spa')->plainTextToken;

    $this->getJson('/api/sync/v1/_probe', ['Authorization' => "Bearer {$userToken}"])
        ->assertUnauthorized();
});

it('does not let a device token authenticate a web route', function () {
    $device = activeDeviceFor(app('currentTenant'));
    $token = $device->createToken('sync', Device::TOKEN_ABILITIES)->plainTextToken;

    $this->getJson('/dashboard', ['Authorization' => "Bearer {$token}"])
        ->assertUnauthorized();
});

it('mints device tokens with the four sync abilities', function () {
    $device = activeDeviceFor(app('currentTenant'));
    $device->createToken('sync', Device::TOKEN_ABILITIES);

    $abilities = $device->tokens()->first()->abilities;

    expect($abilities)->toBe(['sync:snapshot', 'sync:pull', 'sync:push', 'sync:attach']);
});
