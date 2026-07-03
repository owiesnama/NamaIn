<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->cashAccount = TreasuryAccount::create([
        'name' => 'Main Cash',
        'type' => 'cash',
        'opening_balance' => 0,
        'currency' => 'SDG',
        'is_active' => true,
    ]);

    $this->product = Product::factory()->create();
    $this->unit = $this->product->units()->create([
        'name' => 'Piece',
        'conversion_factor' => 1,
    ]);

    $this->storage = Storage::factory()->create();
    $this->storage->addStock($this->product->id, 100, 'initial');
});

test('sale creation with cash payment should record treasury movement', function () {
    $customer = Customer::factory()->create();
    $balanceBefore = $this->cashAccount->currentBalance();

    $response = $this->post(route('sales.store'), [
        'total' => 500,
        'invocable' => [
            'id' => $customer->id,
            'name' => $customer->name,
            'type' => 'App\Models\Customer',
        ],
        'products' => [
            [
                'product' => $this->product->id,
                'quantity' => 2,
                'unit' => $this->unit->id,
                'price' => 250,
                'storage' => $this->storage->id,
            ],
        ],
        'payment_method' => 'cash',
        'initial_payment_amount' => 500,
        'treasury_account_id' => $this->cashAccount->id,
        'discount' => 0,
    ]);

    $response->assertRedirect();

    // Payment should exist
    $payment = Payment::latest()->first();
    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe(500.0);

    // Treasury movement should exist
    $movement = TreasuryMovement::where('treasury_account_id', $this->cashAccount->id)->latest()->first();
    expect($movement)->not->toBeNull('Treasury movement was NOT created — this is the bug');
    expect($movement->amount)->toBe(50000); // 500 * 100 cents

    // Treasury balance should increase
    expect($this->cashAccount->fresh()->currentBalance())
        ->toBe($balanceBefore + 50000);
});

test('an untouched payment section records no payment at all', function () {
    $customer = Customer::factory()->create();

    $this->post(route('sales.store'), [
        'total' => 500,
        'invocable' => ['id' => $customer->id, 'name' => $customer->name, 'type' => 'App\Models\Customer'],
        'products' => [
            ['product' => $this->product->id, 'quantity' => 2, 'unit' => $this->unit->id, 'price' => 250, 'storage' => $this->storage->id],
        ],
        'payment_method' => 'cash',
        'discount' => 0,
    ])->assertRedirect();

    $invoice = Invoice::latest('id')->first();

    expect($invoice->paid_amount)->toEqual(0)
        ->and(Payment::count())->toBe(0)
        ->and(TreasuryMovement::count())->toBe(0);
});

test('a cash payment without an explicit account lands in the default cash account', function () {
    $customer = Customer::factory()->create();

    $this->post(route('sales.store'), [
        'total' => 500,
        'invocable' => ['id' => $customer->id, 'name' => $customer->name, 'type' => 'App\Models\Customer'],
        'products' => [
            ['product' => $this->product->id, 'quantity' => 2, 'unit' => $this->unit->id, 'price' => 250, 'storage' => $this->storage->id],
        ],
        'payment_method' => 'cash',
        'initial_payment_amount' => 500,
        'discount' => 0,
    ])->assertRedirect();

    $payment = Payment::latest('id')->first();
    $movement = TreasuryMovement::where('treasury_account_id', $this->cashAccount->id)->latest('id')->first();

    expect($payment)->not->toBeNull()
        ->and($payment->treasury_account_id)->toBe($this->cashAccount->id)
        ->and($movement)->not->toBeNull()
        ->and($movement->amount)->toBe(50000);
});

test('a cash purchase payment without an explicit account debits the default cash account', function () {
    $supplier = Supplier::factory()->create();

    $this->post(route('purchases.store'), [
        'total' => 300,
        'invocable' => ['id' => $supplier->id, 'name' => $supplier->name, 'type' => 'App\Models\Supplier'],
        'products' => [
            ['product' => $this->product->id, 'quantity' => 1, 'unit' => $this->unit->id, 'price' => 300, 'storage' => $this->storage->id],
        ],
        'payment_method' => 'cash',
        'initial_payment_amount' => 300,
        'discount' => 0,
    ])->assertRedirect();

    $movement = TreasuryMovement::where('treasury_account_id', $this->cashAccount->id)->latest('id')->first();

    expect($movement)->not->toBeNull()
        ->and($movement->amount)->toBe(-30000);
});

test('an explicitly selected account wins over the default cash account', function () {
    $other = TreasuryAccount::create([
        'name' => 'Second Drawer',
        'type' => 'cash',
        'opening_balance' => 0,
        'currency' => 'SDG',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create();

    $this->post(route('sales.store'), [
        'total' => 100,
        'invocable' => ['id' => $customer->id, 'name' => $customer->name, 'type' => 'App\Models\Customer'],
        'products' => [
            ['product' => $this->product->id, 'quantity' => 1, 'unit' => $this->unit->id, 'price' => 100, 'storage' => $this->storage->id],
        ],
        'payment_method' => 'cash',
        'initial_payment_amount' => 100,
        'treasury_account_id' => $other->id,
        'discount' => 0,
    ])->assertRedirect();

    expect(TreasuryMovement::where('treasury_account_id', $other->id)->count())->toBe(1)
        ->and(TreasuryMovement::where('treasury_account_id', $this->cashAccount->id)->count())->toBe(0);
});
