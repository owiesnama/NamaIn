<?php

use App\Enums\ChequeStatus;
use App\Enums\ChequeType;
use App\Enums\PaymentDirection;
use App\Enums\TreasuryMovementReason;
use App\Models\Bank;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->bank = Bank::create(['name' => 'Test Bank']);
    $this->bankAccount = TreasuryAccount::factory()->bank()->create(['bank_id' => $this->bank->id]);
    $this->clearingAccount = TreasuryAccount::factory()->chequeClearing()->create();
});

// ─────────────────────────────────────────────
// Fix 1.1 — ChequeClearing account required for receivable cheques
// ─────────────────────────────────────────────

test('registering a receivable cheque credits the cheque clearing account', function () {
    $customer = Customer::factory()->create();
    $balanceBefore = $this->clearingAccount->currentBalance();

    $this->post(route('cheques.store'), [
        'payee_id' => $customer->id,
        'payee_type' => get_class($customer),
        'type' => ChequeType::Receivable->value,
        'amount' => 1000,
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-FLOW-01',
        'due' => now()->addDays(30)->toDateString(),
    ])->assertRedirect(route('cheques.index'));

    expect($this->clearingAccount->fresh()->currentBalance())
        ->toBe($balanceBefore + 100000); // 1000 * 100 cents
});

test('registering a receivable cheque is blocked when no cheque clearing account exists', function () {
    $this->clearingAccount->update(['is_active' => false]);
    $customer = Customer::factory()->create();

    $this->post(route('cheques.store'), [
        'payee_id' => $customer->id,
        'payee_type' => get_class($customer),
        'type' => ChequeType::Receivable->value,
        'amount' => 500,
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-FLOW-02',
        'due' => now()->addDays(30)->toDateString(),
    ])->assertRedirect(route('treasury.create'))
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('cheques', ['reference_number' => 'CHQ-FLOW-02']);
});

test('registering a payable cheque does not require a cheque clearing account', function () {
    $this->clearingAccount->update(['is_active' => false]);
    $supplier = Supplier::factory()->create();

    $this->post(route('cheques.store'), [
        'payee_id' => $supplier->id,
        'payee_type' => get_class($supplier),
        'type' => ChequeType::Payable->value,
        'amount' => 750,
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-FLOW-03',
        'due' => now()->addDays(30)->toDateString(),
    ])->assertRedirect(route('cheques.index'));

    $this->assertDatabaseHas('cheques', ['reference_number' => 'CHQ-FLOW-03']);
});

// ─────────────────────────────────────────────
// Fix 1.3 — Bank account debited/credited on cheque clearance
// ─────────────────────────────────────────────

test('clearing a receivable cheque debits clearing account and credits bank account', function () {
    $customer = Customer::factory()->create();
    // Seed the clearing account with enough balance to debit (simulates a prior cheque registration)
    $this->clearingAccount->update(['opening_balance' => 80000]);
    $clearingBefore = $this->clearingAccount->currentBalance();
    $bankBefore = $this->bankAccount->currentBalance();

    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'type' => ChequeType::Receivable->value,
        'amount' => 800,
        'status' => ChequeStatus::Issued,
        'bank_id' => $this->bank->id,
        'bank' => $this->bank->name,
        'reference_number' => 'CHQ-FLOW-04',
    ]);

    $this->put(route('cheques.updateStatus', $cheque->id), [
        'status' => ChequeStatus::Cleared->value,
    ])->assertRedirect();

    expect($this->clearingAccount->fresh()->currentBalance())
        ->toBe($clearingBefore - 80000); // debit 800 * 100

    expect($this->bankAccount->fresh()->currentBalance())
        ->toBe($bankBefore + 80000); // credit 800 * 100

    // Bank credit should use ChequeReceived reason
    $bankCredit = TreasuryMovement::where('treasury_account_id', $this->bankAccount->id)
        ->where('amount', 80000)
        ->latest()
        ->first();
    expect($bankCredit->reason)->toBe(TreasuryMovementReason::ChequeReceived);

    // Cleared payment should have direction = in
    $payment = Payment::where('payable_id', $customer->id)->first();
    expect($payment->direction)->toBe(PaymentDirection::In);
});

test('clearing a payable cheque debits the bank account', function () {
    $supplier = Supplier::factory()->create();
    $this->bankAccount->update(['opening_balance' => 200000]); // start with 2000 SDG
    $bankBefore = $this->bankAccount->currentBalance();

    $cheque = Cheque::factory()->create([
        'chequeable_id' => $supplier->id,
        'chequeable_type' => get_class($supplier),
        'type' => ChequeType::Payable->value,
        'amount' => 600,
        'status' => ChequeStatus::Issued,
        'bank_id' => $this->bank->id,
        'bank' => $this->bank->name,
        'reference_number' => 'CHQ-FLOW-05',
    ]);

    $this->put(route('cheques.updateStatus', $cheque->id), [
        'status' => ChequeStatus::Cleared->value,
    ])->assertRedirect();

    expect($this->bankAccount->fresh()->currentBalance())
        ->toBe($bankBefore - 60000); // debit 600 * 100

    // Bank debit should use ChequeCleared reason
    $bankDebit = TreasuryMovement::where('treasury_account_id', $this->bankAccount->id)
        ->where('amount', -60000)
        ->latest()
        ->first();
    expect($bankDebit->reason)->toBe(TreasuryMovementReason::ChequeCleared);

    // Cleared payment should have direction = out
    $payment = Payment::where('payable_id', $supplier->id)->first();
    expect($payment->direction)->toBe(PaymentDirection::Out);
});

// ─────────────────────────────────────────────
// Fix 1.2 — Treasury account edit persists bank_id
// ─────────────────────────────────────────────

test('returning a receivable cheque reverses the cheque clearing account', function () {
    $customer = Customer::factory()->create();
    $this->clearingAccount->update(['opening_balance' => 90000]);
    $balanceBefore = $this->clearingAccount->currentBalance();

    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'type' => ChequeType::Receivable->value,
        'amount' => 900,
        'status' => ChequeStatus::Issued,
        'bank_id' => $this->bank->id,
        'bank' => $this->bank->name,
        'reference_number' => 'CHQ-FLOW-RETURNED',
    ]);

    $this->put(route('cheques.updateStatus', $cheque->id), [
        'status' => ChequeStatus::Returned->value,
    ])->assertRedirect();

    expect($cheque->fresh()->status)->toBe(ChequeStatus::Returned)
        ->and($this->clearingAccount->fresh()->currentBalance())->toBe($balanceBefore - 90000);

    $movement = TreasuryMovement::where('treasury_account_id', $this->clearingAccount->id)
        ->where('amount', -90000)
        ->latest()
        ->first();

    expect($movement->reason)->toBe(TreasuryMovementReason::ChequeBounced);
});

test('updating a bank-type treasury account persists bank_id', function () {
    $newBank = Bank::create(['name' => 'New Bank']);

    $this->put(route('treasury.update', $this->bankAccount->id), [
        'name' => $this->bankAccount->name,
        'notes' => null,
        'bank_id' => $newBank->id,
    ])->assertRedirect(route('treasury.show', $this->bankAccount->id));

    expect($this->bankAccount->fresh()->bank_id)->toBe($newBank->id);
});
