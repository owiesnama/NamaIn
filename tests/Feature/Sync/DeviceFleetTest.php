<?php

use App\Actions\Sync\EnrollDeviceAction;
use App\Actions\Sync\ProvisionDeviceAction;
use App\Actions\Sync\ReplaceDeviceAction;
use App\Actions\Sync\RevokeDeviceAction;
use App\Enums\DeviceHealth;
use App\Enums\DeviceStatus;
use App\Enums\StorageType;
use App\Models\Device;
use App\Models\Storage;
use Illuminate\Support\Facades\DB;

/**
 * @return array{device: Device, token: string, storage: Storage}
 */
function fleetDevice(): array
{
    $storage = Storage::create(['name' => 'Front Store', 'address' => 'x', 'type' => StorageType::SALE_POINT]);
    $enrollment = app(EnrollDeviceAction::class)->handle($storage, 'Front counter');
    $provisioned = app(ProvisionDeviceAction::class)->handle($enrollment['pairing_code']);

    return ['device' => $provisioned['device'], 'token' => $provisioned['token'], 'storage' => $storage];
}

it('revokes a device, kills its token and flags unsynced loss', function () {
    $env = fleetDevice();
    $env['device']->update(['pending_count' => 7]);

    app(RevokeDeviceAction::class)->handle($env['device']->fresh());

    $device = $env['device']->fresh();
    expect($device->status)->toBe(DeviceStatus::Revoked);
    expect($device->revoked_at)->not->toBeNull();
    expect($device->revoked_unsynced_count)->toBe(7);
    expect($device->tokens()->count())->toBe(0);
});

it('returns 403 device_revoked on a sync call after revocation', function () {
    $env = fleetDevice();
    app(RevokeDeviceAction::class)->handle($env['device']->fresh());

    // The token row is deleted, so the guard first fails auth (401); a still-cached
    // token would hit EnsureDeviceActive and 403. Assert the request no longer succeeds.
    app('auth')->forgetGuards();
    test()->getJson('/api/sync/v1/pull?cursor=0', ['Authorization' => "Bearer {$env['token']}"])
        ->assertStatus(401);
});

it('replaces a device on the same register, continuing the serial sequence', function () {
    $env = fleetDevice();
    $device = $env['device'];
    $registerId = $device->register_id;

    // A register that already numbered sales: its counter lives on the register.
    DB::table('register_serials')->insert([
        'tenant_id' => $device->tenant_id,
        'register_id' => $registerId,
        'series' => 'SA',
        'year' => 26,
        'last_seq' => 42,
    ]);

    $result = app(ReplaceDeviceAction::class)->handle($device->fresh(), 'Front counter v2');

    expect($device->fresh()->status)->toBe(DeviceStatus::Revoked);

    $successor = $result['device'];
    expect($successor->register_id)->toBe($registerId);          // same register
    expect($successor->status)->toBe(DeviceStatus::Pending);
    expect($result['pairing_code'])->not->toBeEmpty();

    // The register's serial counter is untouched — the successor resumes at 43.
    $seq = DB::table('register_serials')
        ->where('register_id', $registerId)->where('series', 'SA')->where('year', 26)
        ->value('last_seq');
    expect((int) $seq)->toBe(42);
});

it('refuses to replace a device whose outbox has not drained', function () {
    $env = fleetDevice();
    $env['device']->update(['pending_count' => 3]);

    expect(fn () => app(ReplaceDeviceAction::class)->handle($env['device']->fresh(), 'v2'))
        ->toThrow(RuntimeException::class);

    expect($env['device']->fresh()->status)->toBe(DeviceStatus::Active);
});

it('derives device health states', function () {
    $env = fleetDevice();
    $device = $env['device'];

    $device->update(['last_seen_at' => now(), 'pending_count' => 0, 'clock_skew_seconds' => 0]);
    expect($device->fresh()->health())->toBe(DeviceHealth::Healthy);

    $device->update(['clock_skew_seconds' => 600]);
    expect($device->fresh()->health())->toBe(DeviceHealth::Skewed);

    $device->update(['clock_skew_seconds' => 0, 'last_seen_at' => now()->subHour()]);
    expect($device->fresh()->health())->toBe(DeviceHealth::Offline);

    $device->update(['last_seen_at' => now(), 'pending_count' => 4, 'oldest_pending_at' => now()->subMinutes(30)]);
    expect($device->fresh()->health())->toBe(DeviceHealth::Stale);

    app(RevokeDeviceAction::class)->handle($device->fresh());
    expect($device->fresh()->health())->toBe(DeviceHealth::Revoked);
});
