<?php

use App\Enums\ChequeStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->bank = Bank::create(['name' => 'Test Bank']);
    $this->bankAccount = TreasuryAccount::factory()->bank()->withOpeningBalance(1_000_000)->create(['bank_id' => $this->bank->id]);
    $this->clearingAccount = TreasuryAccount::factory()->chequeClearing()->withOpeningBalance(1_000_000)->create();
});

test('clearing a credit cheque with invoice records payment on that invoice', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => get_class($customer),
        'total' => 1000,
        'discount' => 0,
        'paid_amount' => 0,
    ]);

    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'invoice_id' => $invoice->id,
        'type' => 1, // Credit
        'amount' => 500,
        'status' => ChequeStatus::Issued,
        'bank' => 'Test Bank',
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-1',
    ]);

    $response = $this->put(route('cheques.updateStatus', $cheque->id), [
        'status' => ChequeStatus::Cleared->value,
    ]);

    $response->assertRedirect();
    $this->assertEquals(500, $invoice->fresh()->paid_amount);
    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'amount' => 500,
        'payment_method' => PaymentMethod::Cheque->value,
    ]);
});

test('clearing a debit cheque with invoice records payment on that invoice', function () {
    $supplier = Supplier::factory()->create();
    $invoice = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => get_class($supplier),
        'total' => 1000,
        'discount' => 0,
        'paid_amount' => 0,
    ]);

    $cheque = Cheque::factory()->create([
        'chequeable_id' => $supplier->id,
        'chequeable_type' => get_class($supplier),
        'invoice_id' => $invoice->id,
        'type' => 0, // Debit
        'amount' => 500,
        'status' => ChequeStatus::Issued,
        'bank' => 'Test Bank',
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-2',
    ]);

    $response = $this->put(route('cheques.updateStatus', $cheque->id), [
        'status' => ChequeStatus::Cleared->value,
    ]);

    $response->assertRedirect();
    $this->assertEquals(500, $invoice->fresh()->paid_amount);
});

test('clearing a credit cheque without invoice creates a direct customer payment', function () {
    $customer = Customer::factory()->create(['opening_balance' => 0]);

    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'type' => 1, // Credit
        'amount' => 500,
        'status' => ChequeStatus::Issued,
        'bank' => 'Test Bank',
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-3',
    ]);

    $response = $this->put(route('cheques.updateStatus', $cheque->id), [
        'status' => ChequeStatus::Cleared->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('payments', [
        'payable_id' => $customer->id,
        'payable_type' => get_class($customer),
        'amount' => 500,
    ]);
    // Balance should decrease (more negative if we use the same calculation)
    // Actually, calculateAccountBalance subtracts direct payments from invoice balance.
    $this->assertEquals(-500, $customer->fresh()->account_balance);

    // Cleared receivable cheque payment should have direction = in
    $payment = Payment::where('payable_id', $customer->id)->first();
    expect($payment->direction)->toBe(PaymentDirection::In);
});

test('clearing a debit cheque without invoice creates a direct supplier payment', function () {
    $supplier = Supplier::factory()->create(['opening_balance' => 0]);

    $cheque = Cheque::factory()->create([
        'chequeable_id' => $supplier->id,
        'chequeable_type' => get_class($supplier),
        'type' => 0, // Debit
        'amount' => 500,
        'status' => ChequeStatus::Issued,
        'bank' => 'Test Bank',
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-4',
    ]);

    $response = $this->put(route('cheques.updateStatus', $cheque->id), [
        'status' => ChequeStatus::Cleared->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('payments', [
        'payable_id' => $supplier->id,
        'payable_type' => get_class($supplier),
        'amount' => 500,
    ]);
    $this->assertEquals(-500, $supplier->fresh()->account_balance);

    // Cleared payable cheque payment should have direction = out
    $payment = Payment::where('payable_id', $supplier->id)->first();
    expect($payment->direction)->toBe(PaymentDirection::Out);
});

test('partially clearing a cheque records cleared_amount, not full amount', function () {
    $customer = Customer::factory()->create();

    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'type' => 1, // Credit
        'amount' => 1000,
        'status' => ChequeStatus::Issued,
        'bank' => 'Test Bank',
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-5',
    ]);

    $response = $this->put(route('cheques.updateStatus', $cheque->id), [
        'status' => ChequeStatus::PartiallyCleared->value,
        'cleared_amount' => 400,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('cheques', [
        'id' => $cheque->id,
        'status' => ChequeStatus::PartiallyCleared->value,
        'cleared_amount' => 400,
    ]);
    $this->assertDatabaseHas('payments', [
        'payable_id' => $customer->id,
        'amount' => 400,
    ]);
});

test('clearing a partially cleared cheque again records the remaining amount', function () {
    $customer = Customer::factory()->create();

    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'type' => 1, // Credit
        'amount' => 1000,
        'cleared_amount' => 400,
        'status' => ChequeStatus::PartiallyCleared,
        'bank' => 'Test Bank',
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-6',
    ]);

    $response = $this->put(route('cheques.updateStatus', $cheque->id), [
        'status' => ChequeStatus::Cleared->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('cheques', [
        'id' => $cheque->id,
        'status' => ChequeStatus::Cleared->value,
        'cleared_amount' => 1000,
    ]);
    // Should have a new payment for the remaining 600
    $this->assertDatabaseHas('payments', [
        'payable_id' => $customer->id,
        'amount' => 600,
    ]);
});

test('a cleared cheque cannot be edited', function () {
    $customer = Customer::factory()->create();
    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'status' => ChequeStatus::Cleared,
    ]);

    $response = $this->get(route('cheques.edit', $cheque->id));
    $response->assertRedirect(route('cheques.index'));
    $response->assertSessionHas('error');

    $response = $this->put(route('cheques.update', $cheque->id), [
        'payee_id' => $customer->id,
        'payee_type' => get_class($customer),
        'type' => 1,
        'due' => now()->toDateString(),
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-X',
        'amount' => 2000,
    ]);
    $response->assertStatus(403);
});

test('a drafted cheque can be edited', function () {
    $customer = Customer::factory()->create();
    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'status' => ChequeStatus::Drafted,
    ]);

    $response = $this->get(route('cheques.edit', $cheque->id));
    $response->assertOk();
});

test('a drafted cheque can be deleted', function () {
    $customer = Customer::factory()->create();
    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'status' => ChequeStatus::Drafted,
    ]);

    $response = $this->delete(route('cheques.destroy', $cheque->id));
    $response->assertRedirect();
    $this->assertSoftDeleted('cheques', ['id' => $cheque->id]);
});

test('a non-drafted non-cancelled cheque cannot be deleted', function () {
    $customer = Customer::factory()->create();
    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'status' => ChequeStatus::Issued,
    ]);

    $response = $this->delete(route('cheques.destroy', $cheque->id));
    $response->assertRedirect();
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('cheques', ['id' => $cheque->id, 'deleted_at' => null]);
});
