<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it can set credit_limit on customer creation', function () {
    $data = [
        'name' => 'John Doe',
        'address' => '123 Test Street, Testing City',
        'phone_number' => '0123456789',
        'credit_limit' => 5000,
    ];

    $response = $this->post(route('customers.store'), $data);

    $response->assertRedirect(route('customers.index'));
    $this->assertDatabaseHas('customers', [
        'name' => 'John Doe',
        'credit_limit' => 5000,
    ]);
});

test('it can update credit_limit of a customer', function () {
    $customer = Customer::factory()->create(['credit_limit' => 1000]);

    $data = [
        'name' => 'Jane Doe',
        'address' => '456 Another Street, Testing City',
        'phone_number' => '9876543210',
        'credit_limit' => 7500,
    ];

    $response = $this->put(route('customers.update', $customer), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'Jane Doe',
        'credit_limit' => 7500,
    ]);
});

test('customer account shows payments not related to an invoice', function () {
    $customer = Customer::factory()->create();

    // Payment related to an invoice
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
    ]);
    $payment1 = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 100,
    ]);

    // Direct payment to customer (not related to invoice)
    $payment2 = Payment::factory()->create([
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'invoice_id' => null,
        'amount' => 200,
        'paid_at' => now()->addMinutes(10), // Ensure it's latest
    ]);

    $response = $this->get(route('customers.account', $customer));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Customers/Account')
        ->has('payments.data', 2)
        ->where('payments.data.0.amount', '200.00')
        ->where('payments.data.1.amount', '100.00')
    );
});

test('it can set payment date on payment creation', function () {
    $customer = Customer::factory()->create();
    $customDate = '2023-01-01 10:00:00';

    $data = [
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 500,
        'payment_method' => 'cash',
        'paid_at' => $customDate,
    ];

    $response = $this->post(route('payments.store'), $data);

    $response->assertRedirect(route('payments.index'));
    $this->assertDatabaseHas('payments', [
        'payable_id' => $customer->id,
        'amount' => 500,
        'paid_at' => $customDate,
    ]);
});

test('it can set payment date on invoice payment recording', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 1000,
    ]);
    $customDate = '2023-02-02 12:00:00';

    $data = [
        'invoice_id' => $invoice->id,
        'amount' => 500,
        'payment_method' => 'cash',
        'paid_at' => $customDate,
    ];

    $response = $this->post(route('payments.store'), $data);

    $response->assertRedirect(route('payments.index'));
    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'amount' => 500,
        'paid_at' => $customDate,
    ]);
});
