<?php

use App\Actions\RecordCustomerAdvanceAction;
use App\Actions\SettleCustomerAdvanceAction;
use App\Enums\CustomerAdvanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TreasuryMovementReason;
use App\Exceptions\OverSettlementException;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\Invoice;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================
// Model: CustomerAdvance
// ============================================================

test('customer advance has correct relationships', function () {
    $advance = CustomerAdvance::factory()->create();

    expect($advance->customer)->toBeInstanceOf(Customer::class);
    expect($advance->treasuryAccount)->toBeInstanceOf(TreasuryAccount::class);
    expect($advance->createdBy)->toBeInstanceOf(User::class);
});

test('remainingBalance returns amount minus settled_amount', function () {
    $advance = CustomerAdvance::factory()->make(['amount' => 500, 'settled_amount' => 200]);

    expect($advance->remainingBalance())->toBe(300.0);
});

test('isFullySettled returns true when settled_amount equals amount', function () {
    $advance = CustomerAdvance::factory()->settled()->make(['amount' => 500]);

    expect($advance->isFullySettled())->toBeTrue();
});

test('isFullySettled returns false when balance remains', function () {
    $advance = CustomerAdvance::factory()->outstanding()->make(['amount' => 500]);

    expect($advance->isFullySettled())->toBeFalse();
});

test('updateSettlementStatus sets outstanding when no payments', function () {
    $advance = CustomerAdvance::factory()->create(['amount' => 300, 'settled_amount' => 0]);

    $advance->updateSettlementStatus();

    expect($advance->status)->toBe(CustomerAdvanceStatus::Outstanding);
    expect((float) $advance->settled_amount)->toBe(0.0);
});

test('updateSettlementStatus sets partially_settled when partial payments exist', function () {
    $advance = CustomerAdvance::factory()->create(['amount' => 300]);

    $advance->payments()->create([
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $advance->created_by,
    ]);

    $advance->updateSettlementStatus();

    expect($advance->status)->toBe(CustomerAdvanceStatus::PartiallySettled);
    expect((float) $advance->settled_amount)->toBe(100.0);
});

test('updateSettlementStatus sets settled when full payments exist', function () {
    $advance = CustomerAdvance::factory()->create(['amount' => 300]);

    $advance->payments()->create([
        'amount' => 300,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $advance->created_by,
    ]);

    $advance->updateSettlementStatus();

    expect($advance->status)->toBe(CustomerAdvanceStatus::Settled);
    expect((float) $advance->settled_amount)->toBe(300.0);
});

test('customer has advances relation', function () {
    $customer = Customer::factory()->create();
    CustomerAdvance::factory()->count(3)->create(['customer_id' => $customer->id]);

    expect($customer->advances)->toHaveCount(3);
});

// ============================================================
// RecordCustomerAdvanceAction
// ============================================================

test('RecordCustomerAdvanceAction creates advance and records treasury debit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create();
    $treasury = TreasuryAccount::factory()->withOpeningBalance(100000)->create();

    $action = app(RecordCustomerAdvanceAction::class);
    $advance = $action->handle(
        customer: $customer,
        amount: 500.00,
        treasury: $treasury,
        actor: $user,
        notes: 'Test advance',
    );

    expect($advance)->toBeInstanceOf(CustomerAdvance::class);
    expect($advance->customer_id)->toBe($customer->id);
    expect((float) $advance->amount)->toBe(500.00);
    expect($advance->status)->toBe(CustomerAdvanceStatus::Outstanding);
    expect($advance->notes)->toBe('Test advance');
    expect($advance->treasury_account_id)->toBe($treasury->id);

    $movement = TreasuryMovement::where('treasury_account_id', $treasury->id)
        ->where('reason', TreasuryMovementReason::CustomerAdvanceGiven)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->amount)->toBe(-50000); // -500 * 100 cents
});

test('RecordCustomerAdvanceAction allows treasury to go negative when balance too low', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create();
    $treasury = TreasuryAccount::factory()->withOpeningBalance(1000)->create(); // 10.00 only

    $action = app(RecordCustomerAdvanceAction::class);

    $advance = $action->handle(
        customer: $customer,
        amount: 500.00,
        treasury: $treasury,
        actor: $user,
    );

    expect($advance)->not->toBeNull();
    expect($treasury->currentBalance())->toBe(1000 - 50000); // went negative
});

// ============================================================
// SettleCustomerAdvanceAction — Scenario 1: Direct repayment
// ============================================================

test('SettleCustomerAdvanceAction records direct repayment and treasury credit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $treasury = TreasuryAccount::factory()->withOpeningBalance(100000)->create();
    $advance = CustomerAdvance::factory()->create([
        'amount' => 500,
        'settled_amount' => 0,
        'status' => CustomerAdvanceStatus::Outstanding,
        'treasury_account_id' => $treasury->id,
    ]);

    // Simulate treasury already debited when advance was given (so repayment doesn't overflow)
    DB::table('treasury_accounts')->where('id', $treasury->id)
        ->update(['opening_balance' => 50000]); // 500.00 remaining in treasury

    $action = app(SettleCustomerAdvanceAction::class);
    $payment = $action->handle(
        advance: $advance,
        amount: 500.00,
        treasury: $treasury,
        actor: $user,
    );

    expect($payment->payable_type)->toBe(CustomerAdvance::class);
    expect($payment->payable_id)->toBe($advance->id);
    expect((float) $payment->amount)->toBe(500.00);
    expect($payment->payment_method)->toBe(PaymentMethod::Cash);

    $advance->refresh();
    expect($advance->status)->toBe(CustomerAdvanceStatus::Settled);

    $movement = TreasuryMovement::where('treasury_account_id', $treasury->id)
        ->where('reason', TreasuryMovementReason::CustomerAdvanceRepaid)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->amount)->toBe(50000); // +500 * 100 cents
});

test('SettleCustomerAdvanceAction allows partial repayment', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $treasury = TreasuryAccount::factory()->withOpeningBalance(100000)->create();
    $advance = CustomerAdvance::factory()->create([
        'amount' => 500,
        'settled_amount' => 0,
        'status' => CustomerAdvanceStatus::Outstanding,
        'treasury_account_id' => $treasury->id,
    ]);

    $action = app(SettleCustomerAdvanceAction::class);
    $action->handle(
        advance: $advance,
        amount: 200.00,
        treasury: $treasury,
        actor: $user,
    );

    $advance->refresh();
    expect($advance->status)->toBe(CustomerAdvanceStatus::PartiallySettled);
    expect((float) $advance->settled_amount)->toBe(200.00);
});

test('SettleCustomerAdvanceAction throws OverSettlementException when amount exceeds remaining balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $treasury = TreasuryAccount::factory()->withOpeningBalance(100000)->create();
    $advance = CustomerAdvance::factory()->create([
        'amount' => 500,
        'settled_amount' => 400,
        'status' => CustomerAdvanceStatus::PartiallySettled,
        'treasury_account_id' => $treasury->id,
    ]);

    $action = app(SettleCustomerAdvanceAction::class);

    expect(fn () => $action->handle(
        advance: $advance,
        amount: 200.00,
        treasury: $treasury,
        actor: $user,
    ))->toThrow(OverSettlementException::class);
});

// ============================================================
// SettleCustomerAdvanceAction — Scenario 2: Invoice offset
// ============================================================

test('SettleCustomerAdvanceAction offsets against an invoice and settles advance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create();
    $treasury = TreasuryAccount::factory()->withOpeningBalance(100000)->create();

    $advance = CustomerAdvance::factory()->create([
        'customer_id' => $customer->id,
        'amount' => 500,
        'settled_amount' => 0,
        'status' => CustomerAdvanceStatus::Outstanding,
        'treasury_account_id' => $treasury->id,
    ]);

    $invoice = Invoice::create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 500,
        'paid_amount' => 0,
        'payment_status' => 'unpaid',
    ]);

    $action = app(SettleCustomerAdvanceAction::class);
    $action->handle(
        advance: $advance,
        amount: 500.00,
        treasury: $treasury,
        actor: $user,
        invoice: $invoice,
    );

    $advance->refresh();
    expect($advance->status)->toBe(CustomerAdvanceStatus::Settled);
    expect((float) $advance->settled_amount)->toBe(500.00);

    $invoice->refresh();
    expect((float) $invoice->paid_amount)->toBe(500.00);
});

// ============================================================
// HTTP — Customer Account page includes advances
// ============================================================

test('customer account page returns advances data', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create();
    CustomerAdvance::factory()->count(2)->create(['customer_id' => $customer->id]);

    $response = $this->get(route('customers.account', $customer));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('advances')
    );
});

// ============================================================
// HTTP — Store advance via POST
// ============================================================

test('can record a customer advance via POST', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $customer = Customer::factory()->create();
    $treasury = TreasuryAccount::factory()->withOpeningBalance(1000000)->create();

    $response = $this->post(route('customer-advances.store', $customer), [
        'amount' => 500,
        'treasury_account_id' => $treasury->id,
        'notes' => 'Advance for project',
        'given_at' => now()->toDateString(),
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas(CustomerAdvance::class, [
        'customer_id' => $customer->id,
        'amount' => 500,
    ]);
});

test('can settle a customer advance via POST', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $treasury = TreasuryAccount::factory()->withOpeningBalance(1000000)->create();
    $advance = CustomerAdvance::factory()->create([
        'amount' => 500,
        'settled_amount' => 0,
        'status' => CustomerAdvanceStatus::Outstanding,
        'treasury_account_id' => $treasury->id,
    ]);

    $response = $this->post(route('customer-advances.settle', $advance), [
        'amount' => 500,
        'treasury_account_id' => $treasury->id,
    ]);

    $response->assertRedirect();
    $advance->refresh();
    expect($advance->status)->toBe(CustomerAdvanceStatus::Settled);
});
