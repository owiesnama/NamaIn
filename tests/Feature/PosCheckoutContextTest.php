<?php

use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\Pos\ProcessPosCheckoutAction;
use App\Enums\StockPolicy;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\Role;
use App\Models\StockTransfer;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\ValueObjects\CheckoutContext;
use App\ValueObjects\PresetIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = app('currentTenant');
    seedTenantRoles($this->tenant);

    $cashierRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'cashier')->first();
    $this->cashier = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->cashier, ['role' => 'cashier', 'role_id' => $cashierRole->id, 'is_active' => true]);

    $this->storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->session = app(OpenPosSessionAction::class)->handle($this->storage, 0, $this->cashier);

    $this->register = Register::factory()->create([
        'tenant_id' => $this->tenant->id,
        'storage_id' => $this->storage->id,
    ]);
});

function checkoutData(int $quantity, ?int $customerId = null): Collection
{
    return collect([
        'customer_id' => $customerId ?? test()->customer->id,
        'total' => $quantity * 1000,
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => test()->product->id,
                'quantity' => $quantity,
                'price' => 1000,
            ],
        ],
    ]);
}

function replayContext(?PresetIdentity $preset = null): CheckoutContext
{
    return new CheckoutContext(
        register: test()->register,
        stockPolicy: StockPolicy::AllowNegative,
        executeReplenishment: false,
        preset: $preset,
    );
}

/*
|--------------------------------------------------------------------------
| Web behavior unchanged (explicit cloud-web context)
|--------------------------------------------------------------------------
*/

test('the explicit cloud-web context behaves exactly like the default', function () {
    $this->actingAs($this->cashier);
    $this->storage->addStock($this->product, 10, 'initial_stock', actor: $this->cashier);
    $drawer = TreasuryAccount::factory()->cash()->create([
        'tenant_id' => $this->tenant->id,
        'sale_point_id' => $this->storage->id,
    ]);

    $invoice = app(ProcessPosCheckoutAction::class)->handle(
        $this->session,
        checkoutData(2),
        $this->cashier,
        context: CheckoutContext::cloudWeb($this->tenant->id),
    );

    $cloudRegister = Register::cloudRegisterFor($this->tenant);

    // The drawer movement is stored in minor units: 2000 (major) * 100.
    expect($invoice->register_id)->toBe($cloudRegister->id)
        ->and($invoice->serial_number)->toContain('-R0-')
        ->and($this->storage->fresh()->quantityOf($this->product))->toBe(8)
        ->and($drawer->currentBalance())->toBe(200000);
});

test('the strict policy still throws when stock is insufficient', function () {
    $this->storage->addStock($this->product, 1, 'initial_stock', actor: $this->cashier);

    $strict = new CheckoutContext(
        register: $this->register,
        stockPolicy: StockPolicy::Strict,
        executeReplenishment: false,
    );

    expect(fn () => app(ProcessPosCheckoutAction::class)->handle(
        $this->session,
        checkoutData(3),
        $this->cashier,
        context: $strict,
    ))->toThrow(InsufficientStockException::class);
});

/*
|--------------------------------------------------------------------------
| AllowNegative — the replay / local-runtime stock policy
|--------------------------------------------------------------------------
*/

test('allow-negative checkout oversells without creating stock transfers', function () {
    $this->storage->addStock($this->product, 1, 'initial_stock', actor: $this->cashier);

    $invoice = app(ProcessPosCheckoutAction::class)->handle(
        $this->session,
        checkoutData(3),
        $this->cashier,
        context: replayContext(),
    );

    expect($invoice->register_id)->toBe($this->register->id)
        ->and($invoice->serial_number)->toContain("-{$this->register->code}-")
        ->and($this->storage->fresh()->quantityOf($this->product))->toBe(-2)
        ->and(StockTransfer::count())->toBe(0);
});

test('allow-negative checkout creates a missing stock row and records the movement', function () {
    $invoice = app(ProcessPosCheckoutAction::class)->handle(
        $this->session,
        checkoutData(2),
        $this->cashier,
        context: replayContext(),
    );

    $movement = $this->storage->movements()
        ->where('product_id', $this->product->id)
        ->where('reason', 'sale_delivery')
        ->first();

    expect($invoice->exists)->toBeTrue()
        ->and($this->storage->fresh()->quantityOf($this->product))->toBe(-2)
        ->and($movement->quantity)->toBe(-2)
        ->and($movement->quantity_before)->toBe(0)
        ->and($movement->quantity_after)->toBe(-2);
});

test('allow-negative cash checkout lands in the register drawer', function () {
    $this->actingAs($this->cashier);
    $this->storage->addStock($this->product, 10, 'initial_stock', actor: $this->cashier);
    $salePointDrawer = TreasuryAccount::factory()->cash()->create([
        'tenant_id' => $this->tenant->id,
        'sale_point_id' => $this->storage->id,
    ]);
    $registerDrawer = TreasuryAccount::factory()->cash()->create([
        'tenant_id' => $this->tenant->id,
        'register_id' => $this->register->id,
    ]);

    app(ProcessPosCheckoutAction::class)->handle(
        $this->session,
        checkoutData(2),
        $this->cashier,
        context: replayContext(),
    );

    // The drawer movement is stored in minor units: 2000 (major) * 100.
    expect($registerDrawer->currentBalance())->toBe(200000)
        ->and($salePointDrawer->currentBalance())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| PresetIdentity — replay reproduces identical rows
|--------------------------------------------------------------------------
*/

test('a preset identity is stored verbatim without minting a serial', function () {
    $this->storage->addStock($this->product, 10, 'initial_stock', actor: $this->cashier);

    $preset = new PresetIdentity(
        serialNumber: "INV-SA-26-{$this->register->code}-00042",
        invoicePublicId: strtolower((string) Str::ulid()),
        linePublicIds: [strtolower((string) Str::ulid())],
        paymentPublicId: strtolower((string) Str::ulid()),
    );

    $invoice = app(ProcessPosCheckoutAction::class)->handle(
        $this->session,
        checkoutData(2),
        $this->cashier,
        context: replayContext($preset),
    );

    expect($invoice->serial_number)->toBe($preset->serialNumber)
        ->and($invoice->public_id)->toBe($preset->invoicePublicId)
        ->and($invoice->transactions()->first()->public_id)->toBe($preset->linePublicIds[0])
        ->and($invoice->payments()->first()->public_id)->toBe($preset->paymentPublicId);

    // The serial was pre-minted by the device: the cloud counter must not move.
    $counter = DB::table('register_serials')->where('register_id', $this->register->id)->first();
    expect($counter)->toBeNull();
});
