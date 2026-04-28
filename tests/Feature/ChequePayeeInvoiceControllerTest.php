<?php

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;

beforeEach(function () {
    actingAsTenantUser();
});

test('returns outstanding customer invoices for cheque payee', function () {
    $customer = Customer::factory()->create();
    $supplier = Supplier::factory()->create();

    $outstandingInvoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'serial_number' => 'SALE-001',
        'total' => 100,
        'discount' => 10,
        'paid_amount' => 25,
        'payment_status' => PaymentStatus::PartiallyPaid,
    ]);

    Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'payment_status' => PaymentStatus::Paid,
    ]);

    Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $response = $this->getJson(route('cheques.payee-invoices', [
        'payee_id' => $customer->id,
        'payee_type' => Customer::class,
    ]));

    $response->assertSuccessful();

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.id'))->toBe($outstandingInvoice->id)
        ->and($response->json('0.serial_number'))->toBe('SALE-001')
        ->and((float) $response->json('0.remaining_balance'))->toBe(65.0)
        ->and((float) $response->json('0.total'))->toBe(90.0);
});

test('accepts short supplier payee type when fetching outstanding invoices', function () {
    $supplier = Supplier::factory()->create();

    $invoice = Invoice::factory()->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-001',
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $response = $this->getJson(route('cheques.payee-invoices', [
        'payee_id' => $supplier->id,
        'payee_type' => 'Supplier',
    ]));

    $response->assertSuccessful();

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.id'))->toBe($invoice->id)
        ->and($response->json('0.serial_number'))->toBe('PUR-001');
});

test('validates payee invoice lookup input', function () {
    $response = $this->getJson(route('cheques.payee-invoices', [
        'payee_id' => 'not-an-id',
        'payee_type' => 'Vendor',
    ]));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['payee_id', 'payee_type']);
});
