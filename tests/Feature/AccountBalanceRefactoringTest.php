<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('customer account balance matches refactored logic', function () {
    $customer = Customer::factory()->create(['opening_balance' => 1000]);

    // Create an invoice
    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 500,
        'discount' => 50,
        'paid_amount' => 100,
        'created_at' => Carbon::now()->subDays(5),
    ]);

    // Direct payment
    Payment::factory()->create([
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 200,
        'paid_at' => Carbon::now()->subDays(3),
    ]);

    // Payment on invoice
    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'paid_at' => Carbon::now()->subDays(2),
    ]);

    // Formula: (total - discount) - paid_amount - direct_payments - opening_balance
    // (500 - 50) - 100 - 200 - 1000 = 450 - 100 - 200 - 1000 = -850
    expect($customer->calculateAccountBalance())->toBe(-850.0);
    expect($customer->account_balance)->toBe(-850.0);
});

test('customer account balance with asOfDate matches refactored logic', function () {
    $customer = Customer::factory()->create(['opening_balance' => 1000]);
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
        'paid_at' => Carbon::now()->subDays(6),
    ]);

    // Direct payment AFTER asOfDate
    Payment::factory()->create([
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 150,
        'paid_at' => Carbon::now()->subDays(1),
    ]);

    // Formula with asOfDate: totalInvoiced(pre) - paidOnInvoices(pre) - directPayments(pre) - opening_balance
    // (500 - 50) - 0 - 200 - 1000 = 450 - 200 - 1000 = -750
    expect($customer->calculateAccountBalance($asOfDate))->toBe(-750.0);
});

test('supplier account balance matches refactored logic', function () {
    $supplier = Supplier::factory()->create(['opening_balance' => 500]);

    // Create an invoice
    $invoice = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
        'total' => 1000,
        'discount' => 100,
        'paid_amount' => 300,
    ]);

    // Direct payment (direction = out because we're paying the supplier)
    Payment::factory()->create([
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'amount' => 100,
        'direction' => 'out',
    ]);

    // Formula: (1000 - 100) - 300 - 100 - 500 = 900 - 300 - 100 - 500 = 0
    expect($supplier->calculateAccountBalance())->toBe(0.0);
});
