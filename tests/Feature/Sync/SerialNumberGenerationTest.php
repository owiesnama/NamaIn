<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Register;
use App\Models\Supplier;
use App\Services\Sync\SerialNumberGenerator;
use Illuminate\Support\Carbon;

function makeInvoice(array $overrides = []): Invoice
{
    return Invoice::create(array_merge([
        'invocable_id' => Customer::factory()->create()->id,
        'invocable_type' => Customer::class,
        'total' => 100,
        'paid_amount' => 0,
        'status' => InvoiceStatus::Initial,
        'payment_status' => PaymentStatus::Unpaid,
    ], $overrides));
}

it('numbers cloud sales invoices with the register format and increments the sequence', function () {
    $yy = now()->format('y');

    $first = makeInvoice();
    $second = makeInvoice();

    expect($first->serial_number)->toBe("INV-SA-{$yy}-R0-00001");
    expect($second->serial_number)->toBe("INV-SA-{$yy}-R0-00002");
    expect($first->register_id)->toBe(Register::cloudRegisterFor(app('currentTenant'))->id);
});

it('uses the SU series for purchases and RET for returns', function () {
    $yy = now()->format('y');
    $supplier = Supplier::factory()->create();

    $purchase = makeInvoice(['invocable_id' => $supplier->id, 'invocable_type' => Supplier::class]);
    $return = makeInvoice(['is_inverse' => true]);

    expect($purchase->serial_number)->toBe("INV-SU-{$yy}-R0-00001");
    expect($return->serial_number)->toBe("RET-SA-{$yy}-R0-00001");
});

it('keeps an independent counter per year', function () {
    Carbon::setTestNow('2026-12-31 23:00:00');
    $lastOf2026 = makeInvoice();

    Carbon::setTestNow('2027-01-01 08:00:00');
    $firstOf2027 = makeInvoice();

    expect($lastOf2026->serial_number)->toBe('INV-SA-26-R0-00001');
    expect($firstOf2027->serial_number)->toBe('INV-SA-27-R0-00001');

    Carbon::setTestNow();
});

it('keeps independent counters per register', function () {
    $tenant = app('currentTenant');
    $cloud = Register::cloudRegisterFor($tenant);
    $device = Register::create(['tenant_id' => $tenant->id, 'code' => 'R7', 'is_cloud' => false]);

    $generator = new SerialNumberGenerator;

    expect($generator->generate($cloud, 'INV-SA', 2026))->toBe('INV-SA-26-R0-00001');
    expect($generator->generate($cloud, 'INV-SA', 2026))->toBe('INV-SA-26-R0-00002');
    expect($generator->generate($device, 'INV-SA', 2026))->toBe('INV-SA-26-R7-00001');
});

it('does not renumber an invoice that already has a serial', function () {
    $invoice = makeInvoice(['serial_number' => 'LEGACY-INV-SA-25-42']);

    expect($invoice->serial_number)->toBe('LEGACY-INV-SA-25-42');
});
