<?php

use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\Sync\EnrollDeviceAction;
use App\Actions\Sync\ProvisionDeviceAction;
use App\Enums\StorageType;
use App\Enums\TreasuryAccountType;
use App\Models\CreditBreachFlag;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\OversellReconciliation;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Register;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Enroll + provision a device on a given sale-point storage.
 *
 * @return array{device: Device, token: string, register: Register}
 */
function deviceOn(Storage $storage, string $name): array
{
    $enrollment = app(EnrollDeviceAction::class)->handle($storage, $name);
    $provisioned = app(ProvisionDeviceAction::class)->handle($enrollment['pairing_code']);

    return [
        'device' => $provisioned['device'],
        'token' => $provisioned['token'],
        'register' => $provisioned['device']->register,
    ];
}

function saleMutation(User $actor, string $serial, PosSession $session, Product $product, array $overrides = []): array
{
    $quantity = $overrides['quantity'] ?? 3;
    $priceMinor = 1000;

    $payload = array_merge([
        'session' => $session->public_id,
        'register' => null,
        'customer' => $overrides['customer'] ?? null,
        'customer_type' => 'customer',
        'payment_method' => $overrides['payment_method'] ?? 'cash',
        'serial_number' => $serial,
        'total' => $quantity * $priceMinor,
        'discount' => 0,
        'items' => [
            [
                'public_id' => strtolower((string) Str::ulid()),
                'product' => $product->public_id,
                'unit' => null,
                'quantity' => $quantity,
                'price' => $priceMinor,
                'base_quantity' => $quantity,
            ],
        ],
    ], $overrides['payload'] ?? []);

    return [
        'idempotency_key' => (string) Str::ulid(),
        'type' => 'sale.create',
        'public_id' => strtolower((string) Str::ulid()),
        'actor' => $actor->public_id,
        'occurred_at' => now()->toIso8601String(),
        'payload' => $payload,
    ];
}

function pushSale(string $token, array $mutation): TestResponse
{
    return test()->postJson('/api/sync/v1/push', [
        'protocol' => 1,
        'mutations' => [$mutation],
    ], ['Authorization' => "Bearer {$token}"]);
}

/**
 * @return array{storage: Storage, session: PosSession, product: Product, actor: User}
 */
function saleEnvironment(int $stock = 2): array
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

it('replays a sale and stores the device-minted serial verbatim without re-allocating', function () {
    $env = saleEnvironment(stock: 10);
    $deviceA = deviceOn($env['storage'], 'Register A');
    $serial = 'INV-SA-26-'.$deviceA['register']->code.'-00042';

    $response = pushSale($deviceA['token'], saleMutation($env['actor'], $serial, $env['session'], $env['product']));

    $response->assertOk()
        ->assertJsonPath('results.0.outcome', 'applied')
        ->assertJsonPath('results.0.serial', $serial)
        ->assertJsonPath('results.0.flags.credit_breach', false);

    $invoice = Invoice::where('serial_number', $serial)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->register_id)->toBe($deviceA['register']->id);

    // The device owns its serial counter — cloud never allocates for it.
    expect(DB::table('register_serials')->where('register_id', $deviceA['register']->id)->exists())->toBeFalse();
});

it('records two-device oversell without ever failing a push', function () {
    $env = saleEnvironment(stock: 2);
    $deviceA = deviceOn($env['storage'], 'Register A');
    $deviceB = deviceOn($env['storage'], 'Register B');

    // Register A sells 3 of an on-hand 2 → stock -1, oversold 1.
    $responseA = pushSale($deviceA['token'], saleMutation($env['actor'], 'INV-SA-26-RA-1', $env['session'], $env['product']));
    // Register B sells 3 more → stock -4, oversold 3 (nothing on hand).
    $responseB = pushSale($deviceB['token'], saleMutation($env['actor'], 'INV-SA-26-RB-1', $env['session'], $env['product']));

    $responseA->assertOk()->assertJsonPath('results.0.outcome', 'applied');
    $responseB->assertOk()->assertJsonPath('results.0.outcome', 'applied');

    // Both sales recorded.
    expect(Invoice::count())->toBe(2);

    // Stock went negative — force-deducted, never blocked.
    $stock = DB::table('stocks')
        ->where('storage_id', $env['storage']->id)
        ->where('product_id', $env['product']->id)
        ->value('quantity');
    expect((int) $stock)->toBe(-4);

    // One oversell row per short sale, surfaced in the flags.
    expect(OversellReconciliation::count())->toBe(2);
    expect($responseA->json('results.0.flags.oversell.0.oversold_qty'))->toBe(1);
    expect($responseB->json('results.0.flags.oversell.0.oversold_qty'))->toBe(3);
    expect($responseA->json('results.0.flags.oversell.0.product'))->toBe($env['product']->public_id);
});

it('flags a credit breach without rejecting the sale', function () {
    $env = saleEnvironment(stock: 100);
    $deviceA = deviceOn($env['storage'], 'Register A');

    $customer = Customer::create([
        'name' => 'On Account',
        'phone_number' => '0999',
        'credit_limit' => 100, // major → 10000 minor
    ]);

    $mutation = saleMutation($env['actor'], 'INV-SA-26-RA-9', $env['session'], $env['product'], [
        'payment_method' => 'credit',
        'customer' => $customer->public_id,
        'quantity' => 50, // 50 * 10.00 = 500.00 → 50000 minor, well past the 100.00 limit
    ]);

    $response = pushSale($deviceA['token'], $mutation);

    $response->assertOk()
        ->assertJsonPath('results.0.outcome', 'applied')
        ->assertJsonPath('results.0.flags.credit_breach', true);

    $flag = CreditBreachFlag::first();
    expect($flag)->not->toBeNull();
    expect($flag->credit_limit)->toBe(10000);
    expect($flag->balance_after)->toBe(50000);
    expect($flag->customer_id)->toBe($customer->id);
});

it('does not flag a credit breach within the limit', function () {
    $env = saleEnvironment(stock: 100);
    $deviceA = deviceOn($env['storage'], 'Register A');

    $customer = Customer::create(['name' => 'Within Limit', 'phone_number' => '0999', 'credit_limit' => 1000]);

    $mutation = saleMutation($env['actor'], 'INV-SA-26-RA-8', $env['session'], $env['product'], [
        'payment_method' => 'credit',
        'customer' => $customer->public_id,
        'quantity' => 5, // 50.00 → under the 1000.00 limit
    ]);

    pushSale($deviceA['token'], $mutation)
        ->assertOk()
        ->assertJsonPath('results.0.flags.credit_breach', false);

    expect(CreditBreachFlag::count())->toBe(0);
});

it('rejects a sale whose session has not synced as retriable', function () {
    $env = saleEnvironment(stock: 10);
    $deviceA = deviceOn($env['storage'], 'Register A');

    $mutation = saleMutation($env['actor'], 'INV-SA-26-RA-7', $env['session'], $env['product']);
    $mutation['payload']['session'] = strtolower((string) Str::ulid()); // unknown

    pushSale($deviceA['token'], $mutation)
        ->assertOk()
        ->assertJsonPath('results.0.outcome', 'rejected')
        ->assertJsonPath('results.0.reason', 'unknown_reference');

    expect(Invoice::count())->toBe(0);
});

it('routes a bank-transfer replay to the device-chosen account', function () {
    $env = saleEnvironment(stock: 10);
    $device = deviceOn($env['storage'], 'Register A');

    $bank = TreasuryAccount::factory()->create([
        'type' => TreasuryAccountType::Bank,
        'is_active' => true,
        'register_id' => null,
        'sale_point_id' => null,
    ]);

    $mutation = saleMutation($env['actor'], 'INV-SA-26-RA-9', $env['session'], $env['product']);
    $mutation['payload']['payment_method'] = 'bank_transfer';
    $mutation['payload']['payment']['method'] = 'bank_transfer';
    $mutation['payload']['payment']['account'] = $bank->public_id;

    pushSale($device['token'], $mutation)->assertOk()->assertJsonPath('results.0.outcome', 'applied');

    $payment = Payment::latest('id')->first();
    expect($payment->treasury_account_id)->toBe($bank->id);
});
