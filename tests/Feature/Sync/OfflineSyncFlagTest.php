<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

// The global Pest tenant opts in; these tests exercise the flag-OFF contract.
beforeEach(fn () => app('currentTenant')->disableOffline());

function makeFlagTestInvoice(): Invoice
{
    return Invoice::create([
        'invocable_id' => Customer::factory()->create()->id,
        'invocable_type' => Customer::class,
        'total' => 100,
        'paid_amount' => 0,
        'status' => InvoiceStatus::Initial,
        'payment_status' => PaymentStatus::Unpaid,
    ]);
}

it('keeps the legacy PK-based serial while the flag is off', function () {
    $yy = now()->format('y');

    $invoice = makeFlagTestInvoice();

    expect($invoice->serial_number)->toBe("INV-SA-{$yy}-{$invoice->id}");
    expect($invoice->register_id)->toBeNull();
});

it('records no change-log entries while the flag is off', function () {
    $tenantId = app('currentTenant')->id;

    $product = Product::create(['name' => 'Widget', 'cost' => 5]);
    $product->update(['name' => 'Renamed']);
    $product->delete();

    expect(DB::table('change_log')->where('tenant_id', $tenantId)->count())->toBe(0);
});

it('still mints a public_id while the flag is off', function () {
    $product = Product::create(['name' => 'Widget', 'cost' => 5]);

    expect($product->public_id)->not->toBeNull();
});

it('switches to register serials and change capture once the flag is enabled', function () {
    $yy = now()->format('y');
    $tenantId = app('currentTenant')->id;

    $before = makeFlagTestInvoice();
    expect($before->serial_number)->toBe("INV-SA-{$yy}-{$before->id}");

    enableOfflineSync();

    $after = makeFlagTestInvoice();
    expect($after->serial_number)->toBe("INV-SA-{$yy}-R0-00001");
    expect($after->register_id)->not->toBeNull();
    expect(DB::table('change_log')->where('tenant_id', $tenantId)->count())->toBeGreaterThan(0);
});
