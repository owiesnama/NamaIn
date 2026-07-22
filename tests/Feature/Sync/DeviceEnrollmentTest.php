<?php

use App\Actions\Sync\EnrollDeviceAction;
use App\Enums\DeviceStatus;
use App\Enums\StorageType;
use App\Enums\TreasuryAccountType;
use App\Models\Device;
use App\Models\Register;
use App\Models\Storage;
use App\Models\TreasuryAccount;

function salePoint(): Storage
{
    return Storage::create([
        'name' => 'Front Store',
        'address' => 'x',
        'type' => StorageType::SALE_POINT,
    ]);
}

it('enrolls a device with its own register and cash drawer', function () {
    $this->signIn();
    $storage = salePoint();

    $enrollment = app(EnrollDeviceAction::class)->handle($storage, 'Front counter iPad');

    $device = $enrollment['device'];
    expect($device)->toBeInstanceOf(Device::class);
    expect($device->status)->toBe(DeviceStatus::Pending);
    expect($device->pairing_code_hash)->not->toBeNull();
    expect($device->pairing_expires_at->isFuture())->toBeTrue();

    // one-time plaintext pairing code, stored only as a sha256 hash
    $code = $enrollment['pairing_code'];
    expect(hash('sha256', $code))->toBe($device->pairing_code_hash);

    // device register binds the sale point and gets the next per-tenant code
    $register = $device->register;
    expect($register->storage_id)->toBe($storage->id);
    expect($register->code)->toBe('R1');
    expect($register->isCloud())->toBeFalse();

    // the register owns a cash drawer
    $drawer = TreasuryAccount::where('register_id', $register->id)->first();
    expect($drawer)->not->toBeNull();
    expect($drawer->type)->toBe(TreasuryAccountType::Cash);
    expect($drawer->sale_point_id)->toBe($storage->id);
});

it('assigns sequential register codes per tenant', function () {
    $this->signIn();
    $storage = salePoint();

    $first = app(EnrollDeviceAction::class)->handle($storage, 'Counter 1');
    $second = app(EnrollDeviceAction::class)->handle($storage, 'Counter 2');

    expect($first['device']->register->code)->toBe('R1');
    expect($second['device']->register->code)->toBe('R2');
});

it('exposes enrollment over the web to users with devices.manage', function () {
    $this->signIn();
    $storage = salePoint();

    $response = $this->post(route('devices.store'), [
        'name' => 'Front counter iPad',
        'storage_id' => $storage->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('pairing_code');

    expect(Device::count())->toBe(1);
});

it('rejects enrollment for users without devices.manage', function () {
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    // demote to cashier (no devices.manage)
    $tenant = app('currentTenant');
    $cashier = \App\Models\Role::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)->where('slug', 'cashier')->first();
    $tenant->users()->updateExistingPivot($user->id, ['role' => 'cashier', 'role_id' => $cashier->id]);

    $storage = salePoint();

    $this->post(route('devices.store'), [
        'name' => 'Rogue device',
        'storage_id' => $storage->id,
    ])->assertForbidden();

    expect(Device::count())->toBe(0);
});
