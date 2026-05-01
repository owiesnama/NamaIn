<?php

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\TreasuryMovementReason;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->cashAccount = TreasuryAccount::factory()->cash()->withOpeningBalance(500_000)->create();
});

// ─────────────────────────────────────────────
// IN payment for customer: credits treasury, reduces balance
// ─────────────────────────────────────────────

test('recording an IN payment for a customer credits treasury and reduces customer balance', function () {
    $customer = Customer::factory()->create(['opening_balance' => 0]);
    $balanceBefore = $this->cashAccount->currentBalance();

    $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 300,
        'payment_method' => PaymentMethod::Cash->value,
        'direction' => PaymentDirection::In->value,
        'treasury_account_id' => $this->cashAccount->id,
    ])->assertRedirect(route('payments.index'));

    // Treasury credited (more money came in)
    expect($this->cashAccount->fresh()->currentBalance())
        ->toBe($balanceBefore + 30000); // 300 * 100 cents

    // Customer balance reduced (they owe us less)
    expect($customer->fresh()->account_balance)->toBe(-300.0);
});

// ─────────────────────────────────────────────
// OUT payment for supplier: debits treasury, reduces supplier balance
// ─────────────────────────────────────────────

test('recording an OUT payment for a supplier debits treasury and reduces supplier balance', function () {
    $supplier = Supplier::factory()->create(['opening_balance' => 0]);
    $balanceBefore = $this->cashAccount->currentBalance();

    $this->post(route('payments.store'), [
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'amount' => 200,
        'payment_method' => PaymentMethod::Cash->value,
        'direction' => PaymentDirection::Out->value,
        'treasury_account_id' => $this->cashAccount->id,
    ])->assertRedirect(route('payments.index'));

    // Treasury debited (money went out)
    expect($this->cashAccount->fresh()->currentBalance())
        ->toBe($balanceBefore - 20000); // 200 * 100 cents

    // Supplier balance reduced (we owe them less)
    expect($supplier->fresh()->account_balance)->toBe(-200.0);
});

// ─────────────────────────────────────────────
// Supplier refund (IN payment to supplier): increases supplier balance
// ─────────────────────────────────────────────

test('a supplier refund (IN payment to supplier) increases supplier balance', function () {
    $supplier = Supplier::factory()->create(['opening_balance' => 0]);

    // First pay supplier 500 (out)
    $this->post(route('payments.store'), [
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'amount' => 500,
        'payment_method' => PaymentMethod::Cash->value,
        'direction' => PaymentDirection::Out->value,
    ])->assertRedirect(route('payments.index'));

    expect($supplier->fresh()->account_balance)->toBe(-500.0);

    // Supplier refunds 100 (IN)
    $this->post(route('payments.store'), [
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash->value,
        'direction' => PaymentDirection::In->value,
    ])->assertRedirect(route('payments.index'));

    // Refund should reduce net settled, making supplier balance higher (less settled)
    expect($supplier->fresh()->account_balance)->toBe(-400.0);
});

// ─────────────────────────────────────────────
// Customer refund (OUT payment to customer): increases customer balance
// ─────────────────────────────────────────────

test('a customer refund (OUT payment to customer) increases customer balance', function () {
    $customer = Customer::factory()->create(['opening_balance' => 0]);

    // Customer pays 500 (in)
    $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 500,
        'payment_method' => PaymentMethod::Cash->value,
        'direction' => PaymentDirection::In->value,
    ])->assertRedirect(route('payments.index'));

    expect($customer->fresh()->account_balance)->toBe(-500.0);

    // We refund customer 100 (out)
    $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash->value,
        'direction' => PaymentDirection::Out->value,
    ])->assertRedirect(route('payments.index'));

    // Net settled decreases, balance goes back up
    expect($customer->fresh()->account_balance)->toBe(-400.0);
});

// ─────────────────────────────────────────────
// Payment model helpers
// ─────────────────────────────────────────────

test('payment isIncoming returns true when direction is in', function () {
    $customer = Customer::factory()->create();

    $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash->value,
        'direction' => PaymentDirection::In->value,
    ]);

    $payment = $customer->payments()->first();
    expect($payment->isIncoming())->toBeTrue();
    expect($payment->isOutgoing())->toBeFalse();
    expect($payment->direction)->toBe(PaymentDirection::In);
});

test('payment isOutgoing returns true when direction is out', function () {
    $supplier = Supplier::factory()->create();

    $this->post(route('payments.store'), [
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash->value,
        'direction' => PaymentDirection::Out->value,
    ]);

    $payment = $supplier->payments()->first();
    expect($payment->isIncoming())->toBeFalse();
    expect($payment->isOutgoing())->toBeTrue();
    expect($payment->direction)->toBe(PaymentDirection::Out);
});

// ─────────────────────────────────────────────
// Treasury movement reason auto-selection
// ─────────────────────────────────────────────

test('IN payment auto-selects PaymentReceived treasury reason', function () {
    $customer = Customer::factory()->create();

    $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 150,
        'payment_method' => PaymentMethod::Cash->value,
        'direction' => PaymentDirection::In->value,
        'treasury_account_id' => $this->cashAccount->id,
    ]);

    $movement = TreasuryMovement::where('treasury_account_id', $this->cashAccount->id)->latest()->first();
    expect($movement)->not->toBeNull();
    expect($movement->reason)->toBe(TreasuryMovementReason::PaymentReceived);
    expect($movement->amount)->toBeGreaterThan(0);
});

test('OUT payment auto-selects ExpensePaid treasury reason', function () {
    $supplier = Supplier::factory()->create();

    $this->post(route('payments.store'), [
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'amount' => 150,
        'payment_method' => PaymentMethod::Cash->value,
        'direction' => PaymentDirection::Out->value,
        'treasury_account_id' => $this->cashAccount->id,
    ]);

    $movement = TreasuryMovement::where('treasury_account_id', $this->cashAccount->id)->latest()->first();
    expect($movement)->not->toBeNull();
    expect($movement->reason)->toBe(TreasuryMovementReason::ExpensePaid);
    expect($movement->amount)->toBeLessThan(0);
});

// ─────────────────────────────────────────────
// Validation: direction is required
// ─────────────────────────────────────────────

test('payment store requires direction field', function () {
    $customer = Customer::factory()->create();

    $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash->value,
        // direction omitted
    ])->assertSessionHasErrors(['direction']);
});
