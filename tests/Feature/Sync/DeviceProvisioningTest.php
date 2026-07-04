<?php

use App\Actions\Sync\EnrollDeviceAction;
use App\Enums\DeviceStatus;
use App\Enums\StorageType;
use App\Models\Storage;

/**
 * @return array{device: \App\Models\Device, pairing_code: string}
 */
function enrollDevice(string $name = 'Front counter'): array
{
    test()->signIn();

    $storage = Storage::create([
        'name' => 'Front Store',
        'address' => 'x',
        'type' => StorageType::SALE_POINT,
    ]);

    return app(EnrollDeviceAction::class)->handle($storage, $name);
}

it('exchanges a valid pairing code for a token and identity', function () {
    $enrollment = enrollDevice();

    $response = $this->postJson('/api/sync/v1/provision', [
        'pairing_code' => $enrollment['pairing_code'],
        'device_name' => 'Front counter',
        'app_version' => '1.0.0',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'token',
            'device' => ['public_id', 'name', 'status'],
            'register' => ['public_id', 'code', 'label'],
            'storage' => ['public_id', 'name', 'type'],
            'tenant' => ['public_id', 'name'],
            'drawer' => ['public_id', 'type'],
            'cursor',
            'protocol',
        ])
        ->assertJsonPath('device.status', 'active')
        ->assertJsonPath('register.code', 'R1')
        ->assertJsonPath('cursor', 0)
        ->assertJsonPath('protocol', 1);

    $device = $enrollment['device']->fresh();
    expect($device->status)->toBe(DeviceStatus::Active);
    expect($device->pairing_code_hash)->toBeNull();
    expect($device->pairing_expires_at)->toBeNull();
    expect($device->provisioned_at)->not->toBeNull();
    expect($device->tokens()->count())->toBe(1);
});

it('rejects an unknown pairing code with 422', function () {
    enrollDevice();

    $this->postJson('/api/sync/v1/provision', [
        'pairing_code' => 'WRONG-CODE1',
        'device_name' => 'Front counter',
    ])->assertStatus(422)->assertJsonPath('error', 'invalid_pairing_code');
});

it('rejects an expired pairing code with 410', function () {
    $enrollment = enrollDevice();
    $enrollment['device']->update(['pairing_expires_at' => now()->subMinute()]);

    $this->postJson('/api/sync/v1/provision', [
        'pairing_code' => $enrollment['pairing_code'],
        'device_name' => 'Front counter',
    ])->assertStatus(410)->assertJsonPath('error', 'pairing_expired');
});

it('rejects re-provisioning an already active device with 409', function () {
    $enrollment = enrollDevice();

    // device became active but (hypothetically) kept its hash — replay must 409
    $enrollment['device']->update(['status' => DeviceStatus::Active]);

    $this->postJson('/api/sync/v1/provision', [
        'pairing_code' => $enrollment['pairing_code'],
        'device_name' => 'Front counter',
    ])->assertStatus(409)->assertJsonPath('error', 'already_provisioned');
});

it('a used pairing code cannot be replayed', function () {
    $enrollment = enrollDevice();

    $this->postJson('/api/sync/v1/provision', [
        'pairing_code' => $enrollment['pairing_code'],
        'device_name' => 'Front counter',
    ])->assertCreated();

    $this->postJson('/api/sync/v1/provision', [
        'pairing_code' => $enrollment['pairing_code'],
        'device_name' => 'Front counter',
    ])->assertStatus(422)->assertJsonPath('error', 'invalid_pairing_code');
});
