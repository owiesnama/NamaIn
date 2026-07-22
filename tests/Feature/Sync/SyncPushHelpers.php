<?php

use App\Actions\Sync\EnrollDeviceAction;
use App\Actions\Sync\ProvisionDeviceAction;
use App\Enums\StorageType;
use App\Models\Device;
use App\Models\Register;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Shared push fixtures for the sync feature tests. Required once per file so the
 * helpers are available whether a single file or the whole suite runs.
 *
 * @return array{device: Device, token: string, storage: Storage, register: Register, drawer: TreasuryAccount, actor: User}
 */
function pushEnvironment(): array
{
    $tenant = app('currentTenant');
    seedTenantRoles($tenant);

    $storage = Storage::create([
        'name' => 'Front Store',
        'address' => 'x',
        'type' => StorageType::SALE_POINT,
    ]);

    $enrollment = app(EnrollDeviceAction::class)->handle($storage, 'Front counter');
    $provisioned = app(ProvisionDeviceAction::class)->handle($enrollment['pairing_code']);

    $device = $provisioned['device'];
    $register = $device->register;
    $drawer = TreasuryAccount::where('register_id', $register->id)->first();

    $actor = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($actor, ['role' => 'owner', 'is_active' => true]);

    return [
        'device' => $device,
        'token' => $provisioned['token'],
        'storage' => $storage,
        'register' => $register,
        'drawer' => $drawer,
        'actor' => $actor,
    ];
}

function pushAs(array $env, array $mutations): TestResponse
{
    return test()->postJson('/api/sync/v1/push', [
        'protocol' => 1,
        'app_version' => '1.0.0',
        'mutations' => $mutations,
    ], ['Authorization' => "Bearer {$env['token']}"]);
}

function customerMutation(array $env, string $publicId, array $payload = []): array
{
    return [
        'idempotency_key' => (string) Str::ulid(),
        'type' => 'customer.create',
        'public_id' => $publicId,
        'actor' => $env['actor']->public_id,
        'occurred_at' => now()->toIso8601String(),
        'payload' => array_merge(['name' => 'Sara', 'phone_number' => '0999', 'credit_limit' => 500000], $payload),
    ];
}
