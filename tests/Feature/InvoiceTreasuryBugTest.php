<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Models\Unit;
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
        'treasury_account_id' => $this->cashAccount->id,
        'discount' => 0,
    ]);

    $response->assertRedirect();

    // Payment should exist
    $payment = \App\Models\Payment::latest()->first();
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
