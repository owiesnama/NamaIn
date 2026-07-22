<?php

use App\Actions\Sync\EnrollDeviceAction;
use App\Actions\Sync\ProvisionDeviceAction;
use App\Enums\StorageType;
use App\Exceptions\Sync\ProvisionException;
use App\Models\Storage;

it('refuses enrollment when the tenant has offline disabled', function () {
    app('currentTenant')->disableOffline();

    $storage = Storage::create(['name' => 'Front Store', 'address' => 'x', 'type' => StorageType::SALE_POINT]);

    expect(fn () => app(EnrollDeviceAction::class)->handle($storage, 'Front counter'))
        ->toThrow(ProvisionException::class);
});

it('refuses provisioning with 403 offline_disabled when the flag is turned off after enrollment', function () {
    // Enroll while enabled (the Pest tenant defaults to offline_enabled).
    $storage = Storage::create(['name' => 'Front Store', 'address' => 'x', 'type' => StorageType::SALE_POINT]);
    $enrollment = app(EnrollDeviceAction::class)->handle($storage, 'Front counter');

    // Kill switch flipped off before the device pairs.
    app('currentTenant')->disableOffline();

    test()->postJson('/api/sync/v1/provision', ['pairing_code' => $enrollment['pairing_code']])
        ->assertStatus(403)
        ->assertJsonPath('error', 'offline_disabled');
});

it('allows provisioning when offline is enabled', function () {
    $storage = Storage::create(['name' => 'Front Store', 'address' => 'x', 'type' => StorageType::SALE_POINT]);
    $enrollment = app(EnrollDeviceAction::class)->handle($storage, 'Front counter');

    $result = app(ProvisionDeviceAction::class)->handle($enrollment['pairing_code']);

    expect($result['token'])->not->toBeEmpty();
});
