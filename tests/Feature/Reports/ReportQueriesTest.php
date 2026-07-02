<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Queries\Reports\CustomerAgingQuery;
use App\Queries\Reports\PurchaseReportQuery;
use App\Queries\Reports\SalesReportQuery;
use App\Queries\Reports\SupplierAgingQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function outstandingInvoiceFor(object $party, float $total, float $paid, int $daysOld): Invoice
{
    return Invoice::factory()->create([
        'invocable_id' => $party->id,
        'invocable_type' => $party::class,
        'total' => $total,
        'discount' => 0,
        'paid_amount' => $paid,
        'payment_status' => $paid > 0 ? PaymentStatus::PartiallyPaid : PaymentStatus::Unpaid,
        'status' => InvoiceStatus::Delivered,
        'created_at' => now()->subDays($daysOld),
    ]);
}

function deliveredSaleFor(object $party, float $price, int $quantity): Invoice
{
    $invoice = Invoice::factory()->create([
        'invocable_id' => $party->id,
        'invocable_type' => $party::class,
        'total' => $price * $quantity,
        'discount' => 0,
        'status' => InvoiceStatus::Delivered,
    ]);

    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'quantity' => $quantity,
        'base_quantity' => $quantity,
        'price' => $price,
        'delivered' => true,
        'created_at' => now(),
    ]);

    return $invoice;
}

test('customer aging buckets outstanding balances by invoice age', function () {
    $customer = Customer::factory()->create(['name' => 'Aged Customer']);

    outstandingInvoiceFor($customer, 100, 20, 10); // outstanding 80, bucket 0-30
    outstandingInvoiceFor($customer, 50, 0, 70);   // outstanding 50, bucket 61-90

    $rows = app(CustomerAgingQuery::class)->get();
    $row = collect($rows)->firstWhere('customer_id', $customer->id);

    expect($row['customer_name'])->toBe('Aged Customer')
        ->and($row['bucket_0_30'])->toEqual(80.0)
        ->and($row['bucket_31_60'])->toEqual(0.0)
        ->and($row['bucket_61_90'])->toEqual(50.0)
        ->and($row['bucket_90_plus'])->toEqual(0.0)
        ->and($row['total'])->toEqual(130.0);

    $summary = app(CustomerAgingQuery::class)->summary();

    expect($summary['customer_count'])->toBe(1)
        ->and($summary['bucket_0_30'])->toEqual(80.0)
        ->and($summary['total'])->toEqual(130.0);
});

test('supplier aging buckets outstanding balances by invoice age', function () {
    $supplier = Supplier::factory()->create(['name' => 'Aged Supplier']);

    outstandingInvoiceFor($supplier, 200, 0, 40); // bucket 31-60
    outstandingInvoiceFor($supplier, 75, 25, 95); // outstanding 50, bucket 90+

    $rows = app(SupplierAgingQuery::class)->get($supplier->id);
    $row = collect($rows)->firstWhere('supplier_id', $supplier->id);

    expect($row['supplier_name'])->toBe('Aged Supplier')
        ->and($row['bucket_31_60'])->toEqual(200.0)
        ->and($row['bucket_90_plus'])->toEqual(50.0)
        ->and($row['total'])->toEqual(250.0);

    $summary = app(SupplierAgingQuery::class)->summary($supplier->id);

    expect($summary['supplier_count'])->toBe(1)
        ->and($summary['total'])->toEqual(250.0);
});

test('the sales report sums revenue and derives average order value', function () {
    $customer = Customer::factory()->create();

    deliveredSaleFor($customer, 10.50, 2); // revenue 21.00
    deliveredSaleFor($customer, 5.00, 1);  // revenue 5.00

    $summary = app(SalesReportQuery::class)->summary(now()->subDay(), now()->addDay());

    expect($summary['invoice_count'])->toBe(2)
        ->and($summary['items_sold'])->toBe(3)
        ->and($summary['revenue'])->toEqual(26.0)
        ->and($summary['average_order_value'])->toEqual(13.0);
});

test('the purchase report sums total cost without an average order value', function () {
    $supplier = Supplier::factory()->create();

    deliveredSaleFor($supplier, 8.00, 5); // cost 40.00

    $summary = app(PurchaseReportQuery::class)->summary(now()->subDay(), now()->addDay());

    expect($summary['invoice_count'])->toBe(1)
        ->and($summary['items_purchased'])->toBe(5)
        ->and($summary['total_cost'])->toEqual(40.0)
        ->and($summary)->not->toHaveKey('average_order_value');
});
