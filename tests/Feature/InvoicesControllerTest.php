<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it can show an invoice', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
    ]);

    $response = $this->get(route('invoices.show', $invoice));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Invoice')
        ->has('invoice')
        ->has('storages')
    );
});

test('it renders the invoice print page', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
    ]);

    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'delivered' => true,
    ]);

    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'delivered' => false,
    ]);

    $response = $this->get(route('invoices.print', $invoice));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Invoices/Print')
        ->has('invoice')
        ->has('qr_url')
        ->has('currency')
    );
});

test('it renders the POS receipt page', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
    ]);

    $response = $this->get(route('invoices.receipt', $invoice));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Invoices/Receipt')
        ->has('invoice')
        ->has('currency')
    );
});
