<?php

use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it can list purchases', function () {
    $supplier = Supplier::factory()->create();
    Invoice::factory()->count(3)->create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
    ]);

    $response = $this->get(route('purchases.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Purchases/Index')
        ->has('invoices.data', 3)
    );
});

test('it can show create purchase page', function () {
    Product::factory()->count(3)->create();
    Supplier::factory()->count(5)->create();

    $response = $this->get(route('purchases.create'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Purchases/Create')
        ->has('products')
        ->has('suppliers')
        ->has('payment_methods')
    );
});

test('it can store a purchase with cash payment', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $unit = Unit::factory()->create(['product_id' => $product->id]);
    $storage = Storage::factory()->create();

    $data = [
        'total' => 2000,
        'discount' => 200,
        'invocable' => [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'type' => Supplier::class,
        ],
        'products' => [
            [
                'product' => $product->id,
                'quantity' => 4,
                'unit' => $unit->id,
                'price' => 500,
                'storage' => $storage->id,
            ],
        ],
        'payment_method' => 'cash',
        'payment_reference' => 'CASH-PUR-001',
    ];

    $response = $this->post(route('purchases.store'), $data);

    $response->assertRedirect(route('purchases.index'));
    $this->assertDatabaseHas('invoices', [
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
        'total' => 2000,
        'discount' => 200,
    ]);

    $invoice = Invoice::where('invocable_id', $supplier->id)->first();
    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'amount' => 1800,
        'payment_method' => PaymentMethod::Cash,
        'reference' => 'CASH-PUR-001',
    ]);
});

test('it can store a purchase with bank transfer payment', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::fake('local');
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $unit = Unit::factory()->create(['product_id' => $product->id]);

    // Simulate FilePond async upload
    $file = UploadedFile::fake()->image('receipt.jpg');
    $tempFilename = 'temp-receipt.jpg';
    Illuminate\Support\Facades\Storage::disk('local')->putFileAs('tmp', $file, $tempFilename);

    $data = [
        'total' => 1000,
        'discount' => 0,
        'invocable' => [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'type' => Supplier::class,
        ],
        'products' => [
            [
                'product' => $product->id,
                'quantity' => 1,
                'unit' => $unit->id,
                'price' => 1000,
            ],
        ],
        'payment_method' => PaymentMethod::BankTransfer->value,
        'initial_payment_amount' => 500,
        'payment_reference' => 'BT-001',
        'bank_name' => 'Test Bank',
        'receipt' => $tempFilename,
    ];

    $response = $this->post(route('purchases.store'), $data);

    $response->assertRedirect(route('purchases.index'));

    $invoice = Invoice::latest()->first();
    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'amount' => 500,
        'payment_method' => PaymentMethod::BankTransfer,
    ]);

    $payment = $invoice->payments->first();
    expect($payment->metadata['bank_name'])->toBe('Test Bank');
    Illuminate\Support\Facades\Storage::disk('public')->assertExists($payment->receipt_path);
    Illuminate\Support\Facades\Storage::disk('local')->assertMissing('tmp/'.$tempFilename);
});

test('it can store a purchase with cheque payment', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $unit = Unit::factory()->create(['product_id' => $product->id]);
    $bank = Bank::factory()->create();

    $data = [
        'total' => 3000,
        'discount' => 0,
        'invocable' => [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'type' => Supplier::class,
        ],
        'products' => [
            [
                'product' => $product->id,
                'quantity' => 1,
                'unit' => $unit->id,
                'price' => 3000,
            ],
        ],
        'payment_method' => PaymentMethod::Cheque->value,
        'initial_payment_amount' => 1500,
        'cheque_due_date' => now()->addDays(7)->toDateString(),
        'cheque_bank_id' => $bank->id,
        'cheque_number' => 'CHQ-001',
    ];

    $response = $this->post(route('purchases.store'), $data);

    $response->assertRedirect(route('purchases.index'));

    $invoice = Invoice::latest()->first();
    $this->assertDatabaseHas('cheques', [
        'invoice_id' => $invoice->id,
        'amount' => 1500,
        'reference_number' => 'CHQ-001',
        'type' => 0,
    ]);
});
