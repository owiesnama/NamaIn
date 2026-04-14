<?php

use App\Enums\ChequeStatus;
use App\Models\Bank;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->bank = Bank::create(['name' => 'Test Bank']);
});

test('can register a new cheque via cheques.store', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => get_class($customer),
    ]);

    $response = $this->post(route('cheques.store'), [
        'payee_id' => $customer->id,
        'payee_type' => get_class($customer),
        'invoice_id' => $invoice->id,
        'type' => 1, // Credit
        'amount' => 1250.50,
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-999',
        'due' => now()->addDays(15)->toDateString(),
        'notes' => 'Test notes',
    ]);

    $response->assertRedirect(route('cheques.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('cheques', [
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'invoice_id' => $invoice->id,
        'amount' => 1250.50,
        'bank_id' => $this->bank->id,
        'bank' => 'Test Bank',
        'reference_number' => 'CHQ-999',
        'notes' => 'Test notes',
        'status' => ChequeStatus::Drafted->value,
    ]);
});

test('cheque registration validation fails with missing fields', function () {
    $response = $this->post(route('cheques.store'), []);

    $response->assertSessionHasErrors(['payee_id', 'payee_type', 'type', 'amount', 'bank_id', 'reference_number', 'due']);
});

test('can update status to Issued, Deposited, Returned, Cancelled', function ($status) {
    $customer = Customer::factory()->create();
    $cheque = Cheque::factory()->create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
        'status' => ChequeStatus::Drafted,
    ]);

    $response = $this->put(route('cheques.updateStatus', $cheque->id), [
        'status' => $status->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('cheques', [
        'id' => $cheque->id,
        'status' => $status->value,
    ]);
})->with([
    ChequeStatus::Issued,
    ChequeStatus::Deposited,
    ChequeStatus::Returned,
    ChequeStatus::Cancelled,
]);
