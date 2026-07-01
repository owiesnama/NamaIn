<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('forParty returns invoice-linked and direct payments for the party only', function () {
    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();

    $invoice = Invoice::factory()->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
    ]);

    $viaInvoice = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'payable_id' => $otherCustomer->id, // payable points elsewhere; the invoice link should still match
        'payable_type' => Customer::class,
        'amount' => 100,
    ]);

    $direct = Payment::factory()->create([
        'invoice_id' => null,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 50,
    ]);

    $unrelated = Payment::factory()->create([
        'invoice_id' => null,
        'payable_id' => $otherCustomer->id,
        'payable_type' => Customer::class,
        'amount' => 999,
    ]);

    $ids = Payment::forParty($customer)->pluck('id');

    expect($ids)->toContain($viaInvoice->id)
        ->toContain($direct->id)
        ->not->toContain($unrelated->id);
});
