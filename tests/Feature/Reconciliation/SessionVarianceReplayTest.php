<?php

use App\Actions\Pos\ClosePosSessionAction;
use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\Sync\EnrollDeviceAction;
use App\Actions\Sync\ProvisionDeviceAction;
use App\Enums\ReconciliationType;
use App\Enums\StorageType;
use App\Enums\TreasuryAccountType;
use App\Models\Device;
use App\Models\ReconciliationItem;
use App\Models\Register;
use App\Models\SessionVariance;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * @return array{device: Device, token: string, register: Register, drawer: TreasuryAccount, storage: Storage, actor: User}
 */
function svEnvironment(): array
{
    $tenant = app('currentTenant');
    seedTenantRoles($tenant);

    $storage = Storage::create(['name' => 'Front Store', 'address' => 'x', 'type' => StorageType::SALE_POINT]);
    $enrollment = app(EnrollDeviceAction::class)->handle($storage, 'Front counter');
    $provisioned = app(ProvisionDeviceAction::class)->handle($enrollment['pairing_code']);
    $device = $provisioned['device'];

    $actor = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($actor, ['role' => 'owner', 'is_active' => true]);

    return [
        'device' => $device,
        'token' => $provisioned['token'],
        'register' => $device->register,
        'drawer' => TreasuryAccount::where('register_id', $device->register->id)->first(),
        'storage' => $storage,
        'actor' => $actor,
    ];
}

function svPush(string $token, array $mutations): TestResponse
{
    app('auth')->forgetGuards();

    return test()->postJson('/api/sync/v1/push', [
        'protocol' => 1,
        'mutations' => $mutations,
    ], ['Authorization' => "Bearer {$token}"]);
}

function svMutation(User $actor, string $type, string $sessionPublicId, array $payload): array
{
    return [
        'idempotency_key' => (string) Str::ulid(),
        'type' => $type,
        'public_id' => $sessionPublicId,
        'actor' => $actor->public_id,
        'occurred_at' => now()->toIso8601String(),
        'payload' => $payload,
    ];
}

it('raises a session variance when the offline close disagrees with the drawer', function () {
    $env = svEnvironment();
    $session = strtolower((string) Str::ulid());

    svPush($env['token'], [svMutation($env['actor'], 'pos_session.open', $session, ['opening_float' => 100000])])
        ->assertOk()->assertJsonPath('results.0.outcome', 'applied');

    svPush($env['token'], [svMutation($env['actor'], 'pos_session.close', $session, ['session' => $session, 'closing_float' => 95000])])
        ->assertOk()->assertJsonPath('results.0.outcome', 'applied');

    $variance = SessionVariance::first();
    expect($variance)->not->toBeNull();
    expect($variance->expected_amount)->toBe(100000);
    expect($variance->declared_amount)->toBe(95000);
    expect($variance->variance_amount)->toBe(-5000);
    expect($variance->register_id)->toBe($env['register']->id);
    expect($variance->device_id)->toBe($env['device']->id);
    expect($variance->adjustment_movement_id)->not->toBeNull();

    $item = ReconciliationItem::where('type', ReconciliationType::SessionVariance)->first();
    expect($item)->not->toBeNull();
    expect($item->subject->is($variance))->toBeTrue();
    expect($item->isOpen())->toBeTrue();
});

it('keeps the drawer balanced to the declared amount (money invariant)', function () {
    $env = svEnvironment();
    $session = strtolower((string) Str::ulid());

    svPush($env['token'], [svMutation($env['actor'], 'pos_session.open', $session, ['opening_float' => 100000])])->assertOk();
    svPush($env['token'], [svMutation($env['actor'], 'pos_session.close', $session, ['session' => $session, 'closing_float' => 95000])])->assertOk();

    // The variance is absorbed into a drawer adjustment: the drawer ends at the counted amount.
    expect($env['drawer']->fresh()->currentBalance())->toBe(95000);
});

it('raises nothing when the offline close matches the expected balance', function () {
    $env = svEnvironment();
    $session = strtolower((string) Str::ulid());

    svPush($env['token'], [svMutation($env['actor'], 'pos_session.open', $session, ['opening_float' => 100000])])->assertOk();
    svPush($env['token'], [svMutation($env['actor'], 'pos_session.close', $session, ['session' => $session, 'closing_float' => 100000])])
        ->assertOk()->assertJsonPath('results.0.outcome', 'applied');

    expect(SessionVariance::count())->toBe(0);
    expect(ReconciliationItem::where('type', ReconciliationType::SessionVariance)->count())->toBe(0);
    expect($env['drawer']->fresh()->currentBalance())->toBe(100000);
});

it('does not raise a variance for a cloud R0 session close', function () {
    $tenant = app('currentTenant');
    seedTenantRoles($tenant);

    $storage = Storage::create(['name' => 'Cloud Store', 'address' => 'x', 'type' => StorageType::SALE_POINT]);
    TreasuryAccount::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cloud Drawer',
        'type' => TreasuryAccountType::Cash,
        'sale_point_id' => $storage->id,
        'opening_balance' => 0,
        'currency' => 'SDG',
    ]);
    $actor = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($actor, ['role' => 'owner', 'is_active' => true]);
    $this->actingAs($actor);

    $cloud = Register::cloudRegisterFor($tenant);
    $session = app(OpenPosSessionAction::class)->handle($storage, 100000, $actor, $cloud);

    // A mismatched cloud close still silently absorbs the variance — no inbox item.
    app(ClosePosSessionAction::class)->handle($session, 90000, $actor, $cloud);

    expect(SessionVariance::count())->toBe(0);
    expect(ReconciliationItem::count())->toBe(0);
});
