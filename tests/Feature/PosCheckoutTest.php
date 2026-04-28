<?php

use App\Actions\Pos\OpenPosSessionAction;
use App\Actions\Pos\ProcessPosCheckoutAction;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Role;
use App\Models\Storage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $this->tenant);
    seedTenantRoles($this->tenant);

    $ownerRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'owner')->first();
    $this->owner = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->owner, ['role' => 'owner', 'role_id' => $ownerRole->id, 'is_active' => true]);

    $cashierRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'cashier')->first();
    $this->cashier = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->cashier, ['role' => 'cashier', 'role_id' => $cashierRole->id, 'is_active' => true]);

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

test('preflight returns transfers_required via dedicated endpoint', function () {
    $this->actingAs($this->cashier);

    $response = $this->postJson(route('pos.preflight', ['tenant' => $this->tenant->slug]), [
        'session_id' => $this->session->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => 200]],
    ]);

    $response->assertSuccessful();
    $response->assertJson(['requires_confirmation' => true]);
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

test('credit checkout creates invoice without payment or treasury movement', function () {
    $data = collect([
        'customer_id' => $this->customer->id,
        'total' => 3000,
        'payment_method' => 'credit',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 3,
                'price' => 1000,
            ],
        ],
    ]);

    $invoice = app(ProcessPosCheckoutAction::class)->execute($this->session, $data, $this->cashier);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and((int) $invoice->total)->toBe(3000)
        ->and($invoice->payment_status->value)->toBe('unpaid')
        ->and((int) $invoice->paid_amount)->toBe(0)
        ->and($invoice->payments)->toHaveCount(0)
        ->and($this->storage->fresh()->quantityOf($this->product))->toBe(97);
});

test('credit checkout requires a named customer', function () {
    $data = collect([
        'customer_id' => null,
        'total' => 1000,
        'payment_method' => 'credit',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'price' => 1000,
            ],
        ],
    ]);

    expect(fn () => app(ProcessPosCheckoutAction::class)->execute($this->session, $data, $this->cashier))
        ->toThrow(DomainException::class, 'Credit sales require a named customer.');
});

test('credit checkout via controller fails without customer_id', function () {
    $this->actingAs($this->cashier);

    $response = $this->post(route('pos.checkout', ['tenant' => $this->tenant->slug]), [
        'session_id' => $this->session->id,
        'customer_id' => null,
        'total' => 1000,
        'payment_method' => 'credit',
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'price' => 1000]],
    ]);

    $response->assertSessionHasErrors('customer_id');
});

test('cash checkout still records payment and treasury movement', function () {
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

    expect($invoice->payment_status->value)->toBe('paid')
        ->and((int) $invoice->paid_amount)->toBe(2000)
        ->and($invoice->payments)->toHaveCount(1)
        ->and($invoice->payments->first()->direction->value)->toBe('in');
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

test('checkout is forbidden for users without pos.operate permission', function () {
    $staffRole = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'staff')->first();
    $staff = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($staff, ['role' => 'staff', 'role_id' => $staffRole->id, 'is_active' => true]);

    $this->actingAs($staff);

    $response = $this->post(route('pos.checkout', ['tenant' => $this->tenant->slug]), [
        'session_id' => $this->session->id,
        'customer_id' => $this->customer->id,
        'total' => 1000,
        'payment_method' => 'cash',
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'price' => 1000]],
    ]);

    $response->assertForbidden();
});
