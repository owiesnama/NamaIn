<?php

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('customer account balance matches refactored logic', function () {
    $customer = Customer::factory()->create(['opening_credit' => 1000]);

    // Create an invoice
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 500,
        'discount' => 50,
        'paid_amount' => 0,
        'created_at' => Carbon::now()->subDays(5),
    ]);

    // Direct payment on the customer (standalone, no invoice)
    Payment::factory()->create([
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 200,
        'direction' => PaymentDirection::In,
        'paid_at' => Carbon::now()->subDays(3),
    ]);

    // Payment on invoice via recordPayment (sets both invoice_id AND payable_id)
    $invoice->recordPayment(
        amount: 100,
        method: PaymentMethod::Cash,
        paidAt: Carbon::now()->subDays(2)->toDateTimeString(),
        direction: PaymentDirection::In,
    );

    // Formula: invoicedTotal - totalPaid + opening_debit - opening_credit
    // invoicedTotal = 500 - 50 = 450
    // totalPaid = 200 (direct) + 100 (invoice) = 300 (all via payable relationship)
    // balance = 450 - 300 + 0 - 1000 = -850
    expect($customer->calculateAccountBalance())->toBe(-850.0);
    expect($customer->account_balance)->toBe(-850.0);
});

test('customer account balance with asOfDate matches refactored logic', function () {
    $customer = Customer::factory()->create(['opening_credit' => 1000]);
    $asOfDate = Carbon::now()->subDays(4)->toDateTimeString();

    // Invoice BEFORE asOfDate
    Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 500,
        'discount' => 50,
        'created_at' => Carbon::now()->subDays(10),
    ]);

    // Invoice AFTER asOfDate
    Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 300,
        'discount' => 0,
        'created_at' => Carbon::now()->subDays(2),
    ]);

    // Direct payment BEFORE asOfDate
    Payment::factory()->create([
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 200,
        'direction' => PaymentDirection::In,
        'paid_at' => Carbon::now()->subDays(6),
    ]);

    // Direct payment AFTER asOfDate
    Payment::factory()->create([
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 150,
        'direction' => PaymentDirection::In,
        'paid_at' => Carbon::now()->subDays(1),
    ]);

    // Formula: invoicedTotal(before asOfDate) - totalPaid(before asOfDate) + opening_debit - opening_credit
    // invoicedTotal = 500 - 50 = 450 (only pre-asOfDate invoice)
    // totalPaid = 200 (only pre-asOfDate payment)
    // balance = 450 - 200 + 0 - 1000 = -750
    expect($customer->calculateAccountBalance($asOfDate))->toBe(-750.0);
});

test('supplier account balance matches refactored logic', function () {
    $supplier = Supplier::factory()->create(['opening_credit' => 500]);

    // Create an invoice
    $invoice = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
        'total' => 1000,
        'discount' => 100,
        'paid_amount' => 0,
    ]);

    // Payment on invoice via recordPayment (sets both invoice_id AND payable_id)
    $invoice->recordPayment(
        amount: 300,
        method: PaymentMethod::Cash,
        direction: PaymentDirection::Out,
    );

    // Direct payment (direction = out because we're paying the supplier)
    Payment::factory()->create([
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'amount' => 100,
        'direction' => PaymentDirection::Out,
    ]);

    // Formula: invoicedTotal - totalPaid + opening_debit - opening_credit
    // invoicedTotal = 1000 - 100 = 900
    // totalPaid = 300 (invoice) + 100 (direct) = 400 (all via payable relationship)
    // balance = 900 - 400 + 0 - 500 = 0
    expect($supplier->calculateAccountBalance())->toBe(0.0);
});
