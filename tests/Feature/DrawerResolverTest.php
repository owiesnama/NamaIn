<?php

use App\Actions\Pos\ClosePosSessionAction;
use App\Actions\Pos\OpenPosSessionAction;
use App\Enums\StorageType;
use App\Enums\TreasuryMovementReason;
use App\Models\Register;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\Services\Pos\DrawerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = app('currentTenant');
    seedTenantRoles($this->tenant);

    $this->actor = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->actor, ['role' => 'owner', 'is_active' => true]);

    $this->storage = Storage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => StorageType::SALE_POINT,
    ]);

    $this->salePointDrawer = TreasuryAccount::factory()->cash()->create([
        'tenant_id' => $this->tenant->id,
        'sale_point_id' => $this->storage->id,
    ]);
});

function deviceRegisterWithDrawer(Storage $storage): array
{
    $register = Register::factory()->create([
        'tenant_id' => $storage->tenant_id,
        'storage_id' => $storage->id,
    ]);

    $drawer = TreasuryAccount::factory()->cash()->create([
        'tenant_id' => $storage->tenant_id,
        'register_id' => $register->id,
    ]);

    return [$register, $drawer];
}

/*
|--------------------------------------------------------------------------
| Resolution rules (Design 01 §2.3, ratified amendment)
|--------------------------------------------------------------------------
*/

test('the cloud register resolves the sale-point drawer', function () {
    $cloudRegister = Register::cloudRegisterFor($this->tenant);

    $drawer = app(DrawerResolver::class)->resolve($cloudRegister, $this->storage);

    expect($drawer->id)->toBe($this->salePointDrawer->id);
});

test('a device register resolves its own register drawer', function () {
    [$register, $registerDrawer] = deviceRegisterWithDrawer($this->storage);

    $drawer = app(DrawerResolver::class)->resolve($register, $this->storage);

    expect($drawer->id)->toBe($registerDrawer->id);
});

test('resolveActive skips inactive drawers while resolve does not', function () {
    $this->salePointDrawer->update(['is_active' => false]);
    $cloudRegister = Register::cloudRegisterFor($this->tenant);
    $resolver = app(DrawerResolver::class);

    expect($resolver->resolveActive($cloudRegister, $this->storage))->toBeNull()
        ->and($resolver->resolve($cloudRegister, $this->storage)->id)->toBe($this->salePointDrawer->id);
});

/*
|--------------------------------------------------------------------------
| Session open/close adoption
|--------------------------------------------------------------------------
*/

test('opening a session without a register records the float on the sale-point drawer', function () {
    app(OpenPosSessionAction::class)->handle($this->storage, 5000, $this->actor);

    expect($this->salePointDrawer->currentBalance())->toBe(5000);
});

test('opening a session for a device register records the float on the register drawer', function () {
    [$register, $registerDrawer] = deviceRegisterWithDrawer($this->storage);

    app(OpenPosSessionAction::class)->handle($this->storage, 5000, $this->actor, $register);

    expect($registerDrawer->currentBalance())->toBe(5000)
        ->and($this->salePointDrawer->currentBalance())->toBe(0);
});

test('closing a session without a register reconciles the sale-point drawer', function () {
    $session = app(OpenPosSessionAction::class)->handle($this->storage, 5000, $this->actor);

    app(ClosePosSessionAction::class)->handle($session, 3000, $this->actor);

    expect($this->salePointDrawer->currentBalance())->toBe(3000);
});

test('closing a session for a device register reconciles the register drawer', function () {
    [$register, $registerDrawer] = deviceRegisterWithDrawer($this->storage);
    $session = app(OpenPosSessionAction::class)->handle($this->storage, 5000, $this->actor, $register);

    app(ClosePosSessionAction::class)->handle($session, 3000, $this->actor, $register);

    expect($registerDrawer->currentBalance())->toBe(3000)
        ->and($registerDrawer->movements()->where('reason', TreasuryMovementReason::ManualAdjustment)->count())->toBe(1)
        ->and($this->salePointDrawer->currentBalance())->toBe(0);
});
