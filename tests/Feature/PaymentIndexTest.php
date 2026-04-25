<?php

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// ─────────────────────────────────────────────
// Summary totals
// ─────────────────────────────────────────────

test('payments index returns summary totals', function () {
    $customer = Customer::factory()->create();
    $supplier = Supplier::factory()->create();

    Payment::factory()->create([
        'direction' => 'in',
        'amount' => 500,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    Payment::factory()->create([
        'direction' => 'out',
        'amount' => 200,
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $this->get(route('payments.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->where('summary.total_in', 500)
            ->where('summary.total_out', 200)
        );
});

test('payments index includes payment_methods prop', function () {
    $this->get(route('payments.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->has('payment_methods')
        );
});

// ─────────────────────────────────────────────
// Filter: direction
// ─────────────────────────────────────────────

test('payments index filters by direction in', function () {
    $customer = Customer::factory()->create();

    $inPayment = Payment::factory()->create([
        'direction' => 'in',
        'amount' => 100,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    Payment::factory()->create([
        'direction' => 'out',
        'amount' => 50,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $this->get(route('payments.index', ['direction' => 'in']))
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $inPayment->id)
        );
});

test('payments index filters by direction out', function () {
    $customer = Customer::factory()->create();

    Payment::factory()->create([
        'direction' => 'in',
        'amount' => 100,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $outPayment = Payment::factory()->create([
        'direction' => 'out',
        'amount' => 50,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $this->get(route('payments.index', ['direction' => 'out']))
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $outPayment->id)
        );
});

// ─────────────────────────────────────────────
// Filter: method
// ─────────────────────────────────────────────

test('payments index filters by payment method', function () {
    $customer = Customer::factory()->create();

    $cashPayment = Payment::factory()->create([
        'direction' => 'in',
        'amount' => 100,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    Payment::factory()->create([
        'direction' => 'in',
        'amount' => 200,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::BankTransfer,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $this->get(route('payments.index', ['method' => 'cash']))
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $cashPayment->id)
        );
});

// ─────────────────────────────────────────────
// Filter: date range
// ─────────────────────────────────────────────

test('payments index filters by date range', function () {
    $customer = Customer::factory()->create();

    $oldPayment = Payment::factory()->create([
        'direction' => 'in',
        'amount' => 100,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now()->subDays(10),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $recentPayment = Payment::factory()->create([
        'direction' => 'in',
        'amount' => 200,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $this->get(route('payments.index', ['date_from' => now()->subDays(1)->toDateString()]))
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $recentPayment->id)
        );
});

test('payments index filters by date_to', function () {
    $customer = Customer::factory()->create();

    $oldPayment = Payment::factory()->create([
        'direction' => 'in',
        'amount' => 100,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now()->subDays(10),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    Payment::factory()->create([
        'direction' => 'in',
        'amount' => 200,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $this->get(route('payments.index', ['date_to' => now()->subDays(5)->toDateString()]))
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $oldPayment->id)
        );
});

// ─────────────────────────────────────────────
// Filter: party type
// ─────────────────────────────────────────────

test('payments index filters by party type customer', function () {
    $customer = Customer::factory()->create();
    $supplier = Supplier::factory()->create();

    $customerPayment = Payment::factory()->create([
        'direction' => 'in',
        'amount' => 100,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    Payment::factory()->create([
        'direction' => 'out',
        'amount' => 200,
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $this->get(route('payments.index', ['party_type' => 'Customer']))
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $customerPayment->id)
        );
});

test('payments index filters by party type supplier', function () {
    $customer = Customer::factory()->create();
    $supplier = Supplier::factory()->create();

    Payment::factory()->create([
        'direction' => 'in',
        'amount' => 100,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $supplierPayment = Payment::factory()->create([
        'direction' => 'out',
        'amount' => 200,
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    $this->get(route('payments.index', ['party_type' => 'Supplier']))
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $supplierPayment->id)
        );
});

// ─────────────────────────────────────────────
// Summary reflects active filters
// ─────────────────────────────────────────────

test('summary totals reflect active direction filter', function () {
    $customer = Customer::factory()->create();

    Payment::factory()->create([
        'direction' => 'in',
        'amount' => 300,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    Payment::factory()->create([
        'direction' => 'out',
        'amount' => 150,
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
        'created_by' => $this->user->id,
        'invoice_id' => null,
    ]);

    // When filtering direction=in, total_in should only count matched payments
    // total_out should be 0 since filter excludes out payments
    $this->get(route('payments.index', ['direction' => 'in']))
        ->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->where('summary.total_in', 300)
            ->where('summary.total_out', 0)
        );
});
