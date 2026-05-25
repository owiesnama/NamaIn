<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function makeSaleInvoice(array $overrides = []): Invoice
{
    $customer = Customer::factory()->create();

    return Invoice::factory()->create(array_merge([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 100,
        'discount' => 0,
        'paid_amount' => 0,
        'payment_status' => PaymentStatus::Unpaid,
        'status' => InvoiceStatus::Initial,
    ], $overrides));
}

function makePurchaseInvoice(array $overrides = []): Invoice
{
    $supplier = Supplier::factory()->create();

    return Invoice::factory()->create(array_merge([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
        'total' => 100,
        'discount' => 0,
        'paid_amount' => 0,
        'payment_status' => PaymentStatus::Unpaid,
        'status' => InvoiceStatus::Initial,
    ], $overrides));
}

function productWithUnit(): array
{
    $product = Product::factory()->create();
    $unit = Unit::factory()->create(['product_id' => $product->id, 'conversion_factor' => 1]);

    return [$product, $unit];
}

function payload(Invoice $invoice, Product $product, Unit $unit, float $price = 75, float $quantity = 2): array
{
    return [
        'total' => $price * $quantity,
        'invocable' => [
            'id' => $invoice->invocable_id,
            'name' => $invoice->invocable->name,
            'type' => $invoice->invocable_type,
        ],
        'products' => [[
            'product' => $product->id,
            'unit' => $unit->id,
            'quantity' => $quantity,
            'price' => $price,
            'description' => 'Updated line',
        ]],
        'discount' => 5,
        'payment_method' => PaymentMethod::Cash->value,
    ];
}

test('owner can edit a sale invoice with no payments', function () {
    $invoice = makeSaleInvoice();
    [$product, $unit] = productWithUnit();
    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'storage_id' => Storage::factory()->create()->id,
        'quantity' => 1,
        'price' => 50,
        'delivered' => false,
    ]);

    [$newProduct, $newUnit] = productWithUnit();
    $data = payload($invoice, $newProduct, $newUnit, price: 80, quantity: 3);

    $response = $this->put(route('sales.update', $invoice), $data);

    $response->assertRedirect(route('invoices.show', $invoice));

    $invoice->refresh();
    expect((float) $invoice->total)->toBe(240.0)
        ->and((float) $invoice->discount)->toBe(5.0)
        ->and($invoice->transactions)->toHaveCount(1)
        ->and($invoice->transactions->first()->product_id)->toBe($newProduct->id);
});

test('it can edit a purchase invoice with no payments', function () {
    $invoice = makePurchaseInvoice();
    [$product, $unit] = productWithUnit();
    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'storage_id' => Storage::factory()->create()->id,
        'quantity' => 1,
        'price' => 50,
        'delivered' => false,
    ]);

    [$newProduct, $newUnit] = productWithUnit();
    $data = payload($invoice, $newProduct, $newUnit, price: 80, quantity: 3);

    $response = $this->put(route('purchases.update', $invoice), $data);

    $response->assertRedirect(route('invoices.show', $invoice));
    expect((float) $invoice->refresh()->total)->toBe(240.0);
});

test('it rejects edit when invoice has any payment', function () {
    $invoice = makeSaleInvoice(['paid_amount' => 25]);
    [$product, $unit] = productWithUnit();

    $response = $this->put(route('sales.update', $invoice), payload($invoice, $product, $unit));

    $response->assertForbidden();
});

test('it rejects edit when invoice is delivered', function () {
    $invoice = makeSaleInvoice(['status' => InvoiceStatus::Delivered]);
    [$product, $unit] = productWithUnit();

    $response = $this->put(route('sales.update', $invoice), payload($invoice, $product, $unit));

    $response->assertForbidden();
});

test('it rejects edit form GET for un-editable invoice', function () {
    $invoice = makeSaleInvoice(['paid_amount' => 10]);

    $this->get(route('sales.edit', $invoice))->assertForbidden();
});

test('it allows editing an inverse invoice when no payments', function () {
    $parent = makeSaleInvoice(['status' => InvoiceStatus::Returned]);
    $inverse = makeSaleInvoice([
        'is_inverse' => true,
        'parent_invoice_id' => $parent->id,
    ]);
    [$product, $unit] = productWithUnit();

    $response = $this->put(route('sales.update', $inverse), payload($inverse, $product, $unit));

    $response->assertRedirect(route('invoices.show', $inverse));
});

test('it allows editing a POS invoice when no payments', function () {
    $session = PosSession::factory()->create();
    $invoice = makeSaleInvoice(['pos_session_id' => $session->id]);
    [$product, $unit] = productWithUnit();

    $response = $this->put(route('sales.update', $invoice), payload($invoice, $product, $unit));

    $response->assertRedirect(route('invoices.show', $invoice));
});

test('sales edit route 404s for purchase invoice', function () {
    $invoice = makePurchaseInvoice();
    [$product, $unit] = productWithUnit();

    $this->put(route('sales.update', $invoice), payload($invoice, $product, $unit))->assertNotFound();
});

test('purchases edit route 404s for sale invoice', function () {
    $invoice = makeSaleInvoice();
    [$product, $unit] = productWithUnit();

    $this->put(route('purchases.update', $invoice), payload($invoice, $product, $unit))->assertNotFound();
});

test('edit renders Inertia page with prefill', function () {
    $invoice = makeSaleInvoice();
    [$product, $unit] = productWithUnit();
    Transaction::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'unit_id' => $unit->id,
        'storage_id' => Storage::factory()->create()->id,
        'quantity' => 2,
        'price' => 30,
        'delivered' => false,
    ]);

    $this->get(route('sales.edit', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sales/Edit')
            ->has('invoice')
            ->has('prefill.items', 1)
            ->where('prefill.items.0.product.id', $product->id)
        );
});

test('it validates required products on update', function () {
    $invoice = makeSaleInvoice();

    $this->put(route('sales.update', $invoice), [
        'total' => 0,
        'invocable' => [
            'id' => $invoice->invocable_id,
            'name' => $invoice->invocable->name,
            'type' => $invoice->invocable_type,
        ],
        'products' => [],
    ])->assertSessionHasErrors('products');
});

test('it rejects edit for partially delivered invoices', function () {
    $invoice = makeSaleInvoice(['status' => InvoiceStatus::PartiallyDelivered]);
    [$product, $unit] = productWithUnit();

    $this->put(route('sales.update', $invoice), payload($invoice, $product, $unit))
        ->assertForbidden();
});

test('it rejects edit for returned invoices', function () {
    $invoice = makeSaleInvoice(['status' => InvoiceStatus::Returned]);
    [$product, $unit] = productWithUnit();

    $this->put(route('sales.update', $invoice), payload($invoice, $product, $unit))
        ->assertForbidden();
});

test('it recomputes total server-side and ignores client-supplied total', function () {
    $invoice = makeSaleInvoice();
    [$product, $unit] = productWithUnit();

    $data = payload($invoice, $product, $unit, price: 80, quantity: 3);
    $data['total'] = 1; // client tries to under-report

    $this->put(route('sales.update', $invoice), $data)
        ->assertRedirect(route('invoices.show', $invoice));

    expect((float) $invoice->refresh()->total)->toBe(240.0);
});

test('a non-owner without sales.create permission cannot edit a sale', function () {
    // Build a user attached to the test tenant as `staff` (view-only).
    actingAsTenantUser(null, 'staff');

    $invoice = makeSaleInvoice();
    [$product, $unit] = productWithUnit();

    $this->put(route('sales.update', $invoice), payload($invoice, $product, $unit))
        ->assertForbidden();
});

test('a cashier (sales.create only) cannot edit a purchase invoice', function () {
    actingAsTenantUser(null, 'cashier');

    $invoice = makePurchaseInvoice();
    [$product, $unit] = productWithUnit();

    $this->put(route('purchases.update', $invoice), payload($invoice, $product, $unit))
        ->assertForbidden();
});

test('it cannot edit an invoice belonging to another tenant', function () {
    $otherTenant = Tenant::create(['name' => 'Other Org', 'slug' => 'other-org', 'is_active' => true]);

    // Force tenant_id explicitly so BelongsToTenant's auth-based default doesn't reassign it.
    $crossCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
    $crossInvoice = Invoice::factory()->create([
        'tenant_id' => $otherTenant->id,
        'invocable_id' => $crossCustomer->id,
        'invocable_type' => Customer::class,
        'paid_amount' => 0,
        'status' => InvoiceStatus::Initial,
    ]);

    expect($crossInvoice->tenant_id)->toBe($otherTenant->id);

    [$product, $unit] = productWithUnit();

    $this->put(route('sales.update', $crossInvoice->id), [
        'total' => 100,
        'invocable' => [
            'id' => $crossInvoice->invocable_id,
            'name' => 'whatever',
            'type' => Customer::class,
        ],
        'products' => [[
            'product' => $product->id,
            'unit' => $unit->id,
            'quantity' => 1,
            'price' => 100,
        ]],
    ])->assertNotFound();
});
