<?php

use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\Pos\ProcessPosCheckoutAction;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $this->tenant);

    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner']);

    $this->cashier = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->cashier, ['role' => 'cashier']);

    $this->storage = Storage::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->storage->addStock($this->product, 100, 'initial_stock', actor: $this->owner);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->session = app(OpenPosSessionAction::class)->execute($this->storage, 5000, $this->cashier);
});

test('it can checkout a pos order', function () {
    $data = collect([
        'customer_id' => $this->customer->id,
        'total' => 2000,
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'price' => 1000,
            ],
        ],
    ]);

    $invoice = app(ProcessPosCheckoutAction::class)->execute($this->session, $data, $this->cashier);

    expect($invoice->pos_session_id)->toBe($this->session->id);
    expect((int) $invoice->total)->toBe(2000);
    expect($this->storage->fresh()->quantityOf($this->product))->toBe(98);
});

test('it respects idempotency key', function () {
    $data = collect([
        'customer_id' => $this->customer->id,
        'total' => 2000,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'price' => 2000]],
    ]);

    $key = 'test-idempotency-key';

    $invoice1 = app(ProcessPosCheckoutAction::class)->execute($this->session, $data, $this->cashier, $key);
    $invoice2 = app(ProcessPosCheckoutAction::class)->execute($this->session, $data, $this->cashier, $key);

    expect($invoice1->id)->toBe($invoice2->id);
    expect($this->storage->fresh()->quantityOf($this->product))->toBe(99); // Only deducted once
});

test('it rolls back if stock is insufficient', function () {
    $data = collect([
        'customer_id' => $this->customer->id,
        'total' => 2000,
        'items' => [['product_id' => $this->product->id, 'quantity' => 200, 'price' => 10]],
    ]);

    expect(fn () => app(ProcessPosCheckoutAction::class)->execute($this->session, $data, $this->cashier, null, false))
        ->toThrow(InsufficientStockException::class);

    expect($this->storage->fresh()->quantityOf($this->product))->toBe(100);
});

test('it returns transfers_required if stock is insufficient but available in warehouse', function () {
    $this->actingAs($this->cashier);

    $response = $this->post(route('pos.checkout', ['tenant' => $this->tenant->slug]), [
        'customer_id' => $this->customer->id,
        'session_id' => $this->session->id,
        'total' => 2000,
        'items' => [['product_id' => $this->product->id, 'quantity' => 200, 'price' => 10]],
        'acknowledge_transfers' => false,
    ], [
        'X-Inertia' => 'true',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHas('response', function ($response) {
        return $response['requires_confirmation'] === true;
    });
});

test('it can checkout as a walk-in customer (null customer_id)', function () {
    $data = collect([
        'customer_id' => null,
        'total' => 1000,
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'price' => 1000,
            ],
        ],
    ]);

    $invoice = app(ProcessPosCheckoutAction::class)->execute($this->session, $data, $this->cashier);

    expect($invoice->invocable_id)->not->toBeNull();
    expect($invoice->invocable->name)->toBe('Walk-in Customer');
    expect($invoice->invocable->is_system)->toBeTrue();
    expect($this->storage->fresh()->quantityOf($this->product))->toBe(99);
});

test('it can checkout as a walk-in customer via controller', function () {
    $this->actingAs($this->cashier);

    $response = $this->post(route('pos.checkout', ['tenant' => $this->tenant->slug]), [
        'customer_id' => null,
        'session_id' => $this->session->id,
        'total' => 1000,
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'price' => 10]],
    ], [
        'X-Inertia' => 'true',
    ]);

    $response->assertStatus(302);
    $response->assertRedirect(route('pos.index'));

    $invoice = Invoice::latest()->first();
    expect($invoice->invocable->name)->toBe('Walk-in Customer');
    expect($invoice->invocable->is_system)->toBeTrue();
});
