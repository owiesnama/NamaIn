<?php

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it can list sales', function () {
    $customer = Customer::factory()->create();
    Invoice::factory()->count(3)->create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
    ]);

    $response = $this->get(route('sales.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Sales/Index')
        ->has('invoices.data', 3)
    );
});

test('it can show create sale page', function () {
    Product::factory()->count(3)->create();
    Customer::factory()->count(5)->create();

    $response = $this->get(route('sales.create'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Sales/Create')
        ->has('products')
        ->has('customers')
        ->has('payment_methods')
    );
});

test('it can search customers in create sale page', function () {
    Customer::factory()->create(['name' => 'John Doe']);
    Customer::factory()->create(['name' => 'Jane Smith']);

    $response = $this->get(route('sales.create', ['customer' => 'John']));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Sales/Create')
        ->has('customers', 1)
        ->where('customers.0.name', 'John Doe')
    );
});

test('it can store a sale with cash payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $unit = Unit::factory()->create(['product_id' => $product->id]);
    $storage = Storage::factory()->create();

    $data = [
        'total' => 1000,
        'discount' => 100,
        'invocable' => [
            'id' => $customer->id,
            'name' => $customer->name,
            'type' => Customer::class,
        ],
        'products' => [
            [
                'product' => $product->id,
                'quantity' => 2,
                'unit' => $unit->id,
                'price' => 500,
                'storage' => $storage->id,
            ],
        ],
        'payment_method' => 'cash',
        'payment_reference' => 'CASH-001',
    ];

    $response = $this->post(route('sales.store'), $data);

    $response->assertRedirect(route('sales.index'));
    $this->assertDatabaseHas('invoices', [
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 1000,
        'discount' => 100,
    ]);

    $invoice = Invoice::where('invocable_id', $customer->id)->first();
    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'amount' => 900,
        'payment_method' => PaymentMethod::Cash,
        'reference' => 'CASH-001',
    ]);
});

test('it can store a sale with initial payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $unit = Unit::factory()->create(['product_id' => $product->id]);
    $storage = Storage::factory()->create();

    $data = [
        'total' => 1000,
        'discount' => 0,
        'invocable' => [
            'id' => $customer->id,
            'name' => $customer->name,
            'type' => Customer::class,
        ],
        'products' => [
            [
                'product' => $product->id,
                'quantity' => 2,
                'unit' => $unit->id,
                'price' => 500,
                'storage' => $storage->id,
            ],
        ],
        'payment_method' => 'bank_transfer',
        'initial_payment_amount' => 300,
        'payment_reference' => 'TRANS-001',
        'payment_notes' => 'Some notes',
    ];

    $response = $this->post(route('sales.store'), $data);

    $response->assertRedirect(route('sales.index'));

    $invoice = Invoice::where('invocable_id', $customer->id)->first();
    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'amount' => 300,
        'payment_method' => PaymentMethod::BankTransfer,
        'reference' => 'TRANS-001',
        'notes' => 'Some notes',
    ]);
});
