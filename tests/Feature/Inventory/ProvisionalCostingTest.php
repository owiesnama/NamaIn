<?php

use App\Actions\Purchase\ReceiveGoodsAction;
use App\Jobs\BackfillProvisionalCostsJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Queries\Reports\ProfitAndLossQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

test('a sale of a product with no cost basis is flagged provisional', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['cost' => 0, 'average_cost' => 0]);
    $unit = Unit::factory()->create(['product_id' => $product->id]);
    $storage = Storage::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('sales.store'), [
        'total' => 1000,
        'invocable' => ['id' => $customer->id, 'name' => $customer->name, 'type' => Customer::class],
        'products' => [[
            'product' => $product->id, 'quantity' => 2, 'unit' => $unit->id, 'price' => 500, 'storage' => $storage->id,
        ]],
        'payment_method' => 'cash',
    ])->assertRedirect(route('sales.index'));

    expect(Transaction::where('product_id', $product->id)->first()->cost_provisional)->toBeTrue();
});

test('a sale of a product with a known cost is not provisional', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(); // factory sets average_cost = cost > 0
    $unit = Unit::factory()->create(['product_id' => $product->id]);
    $storage = Storage::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('sales.store'), [
        'total' => 1000,
        'invocable' => ['id' => $customer->id, 'name' => $customer->name, 'type' => Customer::class],
        'products' => [[
            'product' => $product->id, 'quantity' => 2, 'unit' => $unit->id, 'price' => 500, 'storage' => $storage->id,
        ]],
        'payment_method' => 'cash',
    ])->assertRedirect(route('sales.index'));

    expect(Transaction::where('product_id', $product->id)->first()->cost_provisional)->toBeFalse();
});

test('the back-fill job restates provisional lines and clears the flag idempotently', function () {
    $tenant = app('currentTenant');
    $product = Product::factory()->create(['cost' => 0, 'average_cost' => 0]);
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create(['invocable_id' => $customer->id, 'invocable_type' => Customer::class]);
    $transaction = Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'base_quantity' => 5,
        'quantity' => 5,
        'unit_cost' => 0,
        'cost_provisional' => true,
        'delivered' => true,
    ]);

    // A purchase later establishes the real cost (minor units).
    DB::table('products')->where('id', $product->id)->update(['average_cost' => 700]);

    (new BackfillProvisionalCostsJob($tenant->id, $product->id))->handle();

    $transaction->refresh();
    expect($transaction->cost_provisional)->toBeFalse();
    expect($transaction->getRawOriginal('unit_cost'))->toBe(700);

    // Idempotent: nothing left to restate.
    (new BackfillProvisionalCostsJob($tenant->id, $product->id))->handle();
    expect(Transaction::where('cost_provisional', true)->count())->toBe(0);
});

test('receiving a purchase dispatches the provisional back-fill', function () {
    Queue::fake();
    $supplier = Supplier::factory()->create();
    $invoice = Invoice::factory()->create(['invocable_id' => $supplier->id, 'invocable_type' => Supplier::class]);
    $product = Product::factory()->create();
    $storage = Storage::factory()->create();
    $transaction = Transaction::factory()->create([
        'invoice_id' => $invoice->id, 'product_id' => $product->id, 'base_quantity' => 5, 'quantity' => 5, 'delivered' => false,
    ]);

    app(ReceiveGoodsAction::class)->handle($transaction, $storage, 5, User::factory()->create());

    Queue::assertPushed(BackfillProvisionalCostsJob::class);
});

test('profit and loss flags periods that include provisional costs', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create(['invocable_id' => $customer->id, 'invocable_type' => Customer::class]);
    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'delivered' => true,
        'cost_provisional' => true,
        'base_quantity' => 1,
        'quantity' => 1,
        'price' => 100,
        'unit_cost' => 0,
        'created_at' => now(),
    ]);

    $summary = (new ProfitAndLossQuery)->summary(now()->startOfMonth(), now()->endOfMonth());

    expect($summary['has_provisional_costs'])->toBeTrue();
});
