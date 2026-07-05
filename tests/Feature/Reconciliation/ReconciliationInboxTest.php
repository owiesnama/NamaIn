<?php

use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\Sync\EnrollDeviceAction;
use App\Actions\Sync\ProvisionDeviceAction;
use App\Enums\ReconciliationType;
use App\Enums\StorageType;
use App\Models\Device;
use App\Models\ParkedMutation;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ReconciliationItem;
use App\Models\Register;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * @return array{device: Device, token: string, register: Register}
 */
function reconDevice(Storage $storage, string $name): array
{
    $enrollment = app(EnrollDeviceAction::class)->handle($storage, $name);
    $provisioned = app(ProvisionDeviceAction::class)->handle($enrollment['pairing_code']);

    return [
        'device' => $provisioned['device'],
        'token' => $provisioned['token'],
        'register' => $provisioned['device']->register,
    ];
}

/**
 * @return array{storage: Storage, session: PosSession, product: Product, actor: User}
 */
function reconEnvironment(int $stock = 2): array
{
    $tenant = app('currentTenant');
    seedTenantRoles($tenant);

    $storage = Storage::create(['name' => 'Front Store', 'address' => 'x', 'type' => StorageType::SALE_POINT]);
    $product = Product::create(['name' => 'Cola', 'cost' => 5, 'price' => 10, 'currency' => 'SDG']);
    $storage->setStockTo($product, $stock, 'seed');

    $actor = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($actor, ['role' => 'owner', 'is_active' => true]);

    $session = app(OpenPosSessionAction::class)->handle($storage, 0, $actor);

    return ['storage' => $storage, 'session' => $session, 'product' => $product, 'actor' => $actor];
}

function reconSaleMutation(User $actor, string $serial, PosSession $session, Product $product, int $quantity = 3): array
{
    $priceMinor = 1000;

    return [
        'idempotency_key' => (string) Str::ulid(),
        'type' => 'sale.create',
        'public_id' => strtolower((string) Str::ulid()),
        'actor' => $actor->public_id,
        'occurred_at' => now()->toIso8601String(),
        'payload' => [
            'session' => $session->public_id,
            'customer' => null,
            'customer_type' => 'customer',
            'payment_method' => 'cash',
            'serial_number' => $serial,
            'total' => $quantity * $priceMinor,
            'discount' => 0,
            'items' => [[
                'public_id' => strtolower((string) Str::ulid()),
                'product' => $product->public_id,
                'unit' => null,
                'quantity' => $quantity,
                'price' => $priceMinor,
                'base_quantity' => $quantity,
            ]],
        ],
    ];
}

function reconPush(string $token, array $mutation): TestResponse
{
    // Each HTTP request re-authenticates its own device token; within one test
    // process the AuthManager caches the first-resolved guard user, so forget the
    // guards to mirror a real per-request resolution across two devices.
    app('auth')->forgetGuards();

    return test()->postJson('/api/sync/v1/push', [
        'protocol' => 1,
        'mutations' => [$mutation],
    ], ['Authorization' => "Bearer {$token}"]);
}

it('raises one reconciliation item per oversell across two registers', function () {
    $env = reconEnvironment(stock: 2);
    $deviceA = reconDevice($env['storage'], 'Register A');
    $deviceB = reconDevice($env['storage'], 'Register B');

    reconPush($deviceA['token'], reconSaleMutation($env['actor'], 'INV-A-1', $env['session'], $env['product']))
        ->assertOk()->assertJsonPath('results.0.outcome', 'applied');
    reconPush($deviceB['token'], reconSaleMutation($env['actor'], 'INV-B-1', $env['session'], $env['product']))
        ->assertOk()->assertJsonPath('results.0.outcome', 'applied');

    $items = ReconciliationItem::where('type', ReconciliationType::Oversell)->get();

    // Exactly one item per short sale, each attributed to its own register/device.
    expect($items)->toHaveCount(2);
    expect($items->pluck('register_id')->unique()->values()->all())
        ->toEqualCanonicalizing([$deviceA['register']->id, $deviceB['register']->id]);
    expect($items->pluck('device_id')->unique()->values()->all())
        ->toEqualCanonicalizing([$deviceA['device']->id, $deviceB['device']->id]);

    $item = $items->first();
    expect($item->isOpen())->toBeTrue();
    expect($item->actor_user_id)->toBe($env['actor']->id);
    expect($item->subject)->not->toBeNull();
});

it('does not double-raise an oversell item when the sale is re-pushed', function () {
    $env = reconEnvironment(stock: 1);
    $deviceA = reconDevice($env['storage'], 'Register A');
    $mutation = reconSaleMutation($env['actor'], 'INV-A-1', $env['session'], $env['product']);

    reconPush($deviceA['token'], $mutation)->assertOk()->assertJsonPath('results.0.outcome', 'applied');
    expect(ReconciliationItem::where('type', ReconciliationType::Oversell)->count())->toBe(1);

    // Re-push of the same mutation is an idempotent no-op — no duplicate item.
    reconPush($deviceA['token'], $mutation)->assertOk()->assertJsonPath('results.0.outcome', 'already_applied');
    expect(ReconciliationItem::where('type', ReconciliationType::Oversell)->count())->toBe(1);
});

it('parks a terminally-rejected mutation and raises exactly one parked item', function () {
    $env = reconEnvironment(stock: 10);
    $deviceA = reconDevice($env['storage'], 'Register A');

    // A credit sale with no customer is a terminal domain violation, not retriable.
    $mutation = reconSaleMutation($env['actor'], 'INV-A-1', $env['session'], $env['product']);
    $mutation['payload']['payment_method'] = 'credit';
    $mutation['payload']['customer'] = null;

    reconPush($deviceA['token'], $mutation)
        ->assertOk()
        ->assertJsonPath('results.0.outcome', 'rejected')
        ->assertJsonPath('results.0.reason', 'validation_failed');

    $parked = ParkedMutation::where('idempotency_key', $mutation['idempotency_key'])->first();
    expect($parked)->not->toBeNull();
    expect($parked->mutation_type)->toBe('sale.create');
    expect($parked->envelope['payload']['payment_method'])->toBe('credit');

    $items = ReconciliationItem::where('type', ReconciliationType::ParkedMutation)->get();
    expect($items)->toHaveCount(1);
    expect($items->first()->subject->is($parked))->toBeTrue();

    // Re-push of the still-broken mutation does not double-park nor double-raise.
    reconPush($deviceA['token'], $mutation)->assertOk()->assertJsonPath('results.0.outcome', 'rejected');
    expect(ParkedMutation::where('idempotency_key', $mutation['idempotency_key'])->count())->toBe(1);
    expect(ReconciliationItem::where('type', ReconciliationType::ParkedMutation)->count())->toBe(1);
});

it('does not park a retriable unknown-reference rejection', function () {
    $env = reconEnvironment(stock: 10);
    $deviceA = reconDevice($env['storage'], 'Register A');

    $mutation = reconSaleMutation($env['actor'], 'INV-A-1', $env['session'], $env['product']);
    $mutation['payload']['session'] = strtolower((string) Str::ulid()); // unknown session

    reconPush($deviceA['token'], $mutation)
        ->assertOk()
        ->assertJsonPath('results.0.outcome', 'rejected')
        ->assertJsonPath('results.0.reason', 'unknown_reference');

    expect(ParkedMutation::count())->toBe(0);
    expect(ReconciliationItem::count())->toBe(0);
});
