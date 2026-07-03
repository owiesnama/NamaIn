<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Queries\DashboardStatsQuery;
use App\Queries\Reports\ProfitAndLossQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('calculates average cost correctly after multiple purchases', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['cost' => 10]); // Initial default cost

    // Purchase 1: 10 units @ $10
    $purchase1 = Invoice::create([
        'invocable_id' => Supplier::factory()->create()->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-001',
        'total' => 100,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchase1->id,
        'quantity' => 10,
        'base_quantity' => 10,
        'price' => 10,
        'unit_cost' => 10,
        'delivered' => true,
    ]);

    // Purchase 2: 20 units @ $25
    $purchase2 = Invoice::create([
        'invocable_id' => Supplier::factory()->create()->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-002',
        'total' => 500,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchase2->id,
        'quantity' => 20,
        'base_quantity' => 20,
        'price' => 25,
        'unit_cost' => 25,
        'delivered' => true,
    ]);

    // Total Cost = (10 * 10) + (20 * 25) = 100 + 500 = 600
    // Total Qty = 10 + 20 = 30
    // Avg Cost = 600 / 30 = 20

    $product->recalculateAverageCost();
    expect((float) $product->average_cost)->toBe(20.0);
});

test('pending sales and purchases are tracked correctly', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['alert_quantity' => 5]);
    $product->stock()->attach($storage->id, ['quantity' => 10, 'public_id' => strtolower((string) Str::ulid())]);

    // Pending Purchase: 5 units
    $purchase = Invoice::create([
        'invocable_id' => Supplier::factory()->create()->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-PND',
        'total' => 50,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchase->id,
        'quantity' => 5,
        'base_quantity' => 5,
        'price' => 10,
        'delivered' => false,
    ]);

    // Pending Sale: 3 units
    $sale = Invoice::create([
        'invocable_id' => Customer::factory()->create()->id,
        'invocable_type' => Customer::class,
        'serial_number' => 'SAL-PND',
        'total' => 60,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $sale->id,
        'quantity' => 3,
        'base_quantity' => 3,
        'price' => 20,
        'delivered' => false,
    ]);

    $product->refresh();
    expect((float) $product->pending_purchases)->toBe(5.0);
    expect((float) $product->pending_sales)->toBe(3.0);
    expect($product->quantityOnHand())->toBe(10);
    expect((float) $product->available_qty)->toBe(7.0); // 10 - 3
    expect((float) $product->expectedQuantity())->toBe(15.0); // 10 + 5
});

test('inventory value uses average cost', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['cost' => 50]);
    $product->stock()->attach($storage->id, ['quantity' => 10, 'public_id' => strtolower((string) Str::ulid())]);

    // Purchase @ $20 (avg cost will be 20 if it's the only delivered purchase)
    $purchase = Invoice::create([
        'invocable_id' => Supplier::factory()->create()->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-001',
        'total' => 100,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchase->id,
        'quantity' => 5,
        'base_quantity' => 5,
        'price' => 20,
        'unit_cost' => 20,
        'delivered' => true,
    ]);

    $product->recalculateAverageCost();

    $query = new DashboardStatsQuery;
    // 10 units * $20 avg cost = $200
    expect($query->totalInventoryValue())->toBe(200.0);
});

/**
 * Create a delivered purchase line for the product (raises the cost pool).
 */
function deliverPurchase(Product $product, Storage $storage, float|int $baseQuantity, float|int $unitCost): void
{
    $invoice = Invoice::create([
        'invocable_id' => Supplier::factory()->create()->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-'.uniqid(),
        'total' => $baseQuantity * $unitCost,
    ]);

    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $invoice->id,
        'quantity' => $baseQuantity,
        'base_quantity' => $baseQuantity,
        'price' => $unitCost,
        'unit_cost' => $unitCost,
        'delivered' => true,
    ]);
}

/**
 * Create a delivered sale line for the product (draws the pool down).
 */
function deliverSale(Product $product, Storage $storage, float|int $baseQuantity, float|int $unitCost): void
{
    $invoice = Invoice::create([
        'invocable_id' => Customer::factory()->create()->id,
        'invocable_type' => Customer::class,
        'serial_number' => 'SAL-'.uniqid(),
        'total' => $baseQuantity,
    ]);

    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $invoice->id,
        'quantity' => $baseQuantity,
        'base_quantity' => $baseQuantity,
        'price' => $baseQuantity,
        'unit_cost' => $unitCost,
        'delivered' => true,
    ]);
}

test('uses a moving average against stock on hand, not the lifetime purchase history', function () {
    $this->actingAs(User::factory()->create());
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['cost' => 10]);

    // Buy 10 @ $10 → avg 10, on hand 10
    deliverPurchase($product, $storage, baseQuantity: 10, unitCost: 10);
    // Sell 8 → on hand 2 (average unchanged at 10)
    deliverSale($product, $storage, baseQuantity: 8, unitCost: 10);
    // Buy 10 @ $20 → moving avg = (2*10 + 10*20) / 12 = 18.33…
    deliverPurchase($product, $storage, baseQuantity: 10, unitCost: 20);

    $product->recalculateAverageCost();

    // Moving average rounds to 18. The old lifetime cumulative average would be
    // (10*10 + 10*20) / 20 = 15, which would ignore that stock was sold.
    expect((int) $product->average_cost)->toBe(18);
});

test('preserves precision and rounds once, instead of truncating mid-calculation', function () {
    $this->actingAs(User::factory()->create());
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['cost' => 0]);

    // 1@10, 1@11, 1@11 → true average = 32 / 3 = 10.6667 → rounds once, at the
    // end, to the nearest cent: 1066.67 cents → 1067 (10.67). An implementation
    // that truncated intermediates mid-calculation would land on 1066 → 10.66,
    // so asserting the exact cent value proves precision is preserved.
    deliverPurchase($product, $storage, baseQuantity: 1, unitCost: 10);
    deliverPurchase($product, $storage, baseQuantity: 1, unitCost: 11);
    deliverPurchase($product, $storage, baseQuantity: 1, unitCost: 11);

    $product->recalculateAverageCost();

    expect($product->average_cost)->toBe(10.67);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'average_cost' => 1067]);
});

test('keeps the average and COGS on the same base-unit basis under unit conversion', function () {
    $this->actingAs(User::factory()->create());
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['cost' => 0]);

    // A "box" holds 12 base units.
    $box = Unit::create([
        'product_id' => $product->id,
        'name' => 'Box',
        'conversion_factor' => 12,
    ]);

    // Buy 1 box = 12 base units @ $10 per base unit → average_cost = 10 (per base unit).
    $purchaseInvoice = Invoice::create([
        'invocable_id' => Supplier::factory()->create()->id,
        'invocable_type' => Supplier::class,
        'serial_number' => 'PUR-CONV',
        'total' => 120,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchaseInvoice->id,
        'unit_id' => $box->id,
        'quantity' => 1,
        'base_quantity' => 12,
        'price' => 120,
        'unit_cost' => 10,
        'delivered' => true,
    ]);

    $product->recalculateAverageCost();
    expect((int) $product->average_cost)->toBe(10);

    // Sell 1 box, stamping the per-base-unit average as unit_cost.
    $saleInvoice = Invoice::create([
        'invocable_id' => Customer::factory()->create()->id,
        'invocable_type' => Customer::class,
        'serial_number' => 'SAL-CONV',
        'total' => 200,
    ]);
    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $saleInvoice->id,
        'unit_id' => $box->id,
        'quantity' => 1,
        'base_quantity' => 12,
        'price' => 200,
        'unit_cost' => $product->average_cost,
        'delivered' => true,
    ]);

    // COGS must use the same (base-unit) basis as the average: 12 * 10 = 120,
    // not the transaction-unit basis 1 * 10 = 10.
    $summary = (new ProfitAndLossQuery)->summary(
        Carbon::now()->subMonth(),
        Carbon::now()->addMonth(),
    );

    expect($summary['cogs'])->toBe(120.0);
});
