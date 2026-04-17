<?php

use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->bank = Bank::create(['name' => 'Test Bank']);
});

// ============================================
// Payment Index & Search
// ============================================

test('can access payments index', function () {
    $response = $this->get(route('payments.index'));
    $response->assertStatus(200);
});

test('can search payments by invoice serial number', function () {
    $customer = Customer::factory()->create(['name' => 'Searchable Customer']);

    $invoice = Invoice::create([
        'serial_number' => 'TEST-SERIAL-999',
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 100,
    ]);

    $payment = Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'payment_method' => 'cash',
        'paid_at' => now(),
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('payments.index', ['search' => 'TEST-SERIAL-999']));

    $response->assertStatus(200);
    $payments = $response->viewData('page')['props']['payments']['data'];
    expect($payments)->toHaveCount(1);
    expect($payments[0]['id'])->toBe($payment->id);
});

test('can search payments by customer name', function () {
    $customer = Customer::factory()->create(['name' => 'Specific Customer Name']);

    $invoice = Invoice::create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 100,
    ]);

    $payment = Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'payment_method' => 'cash',
        'paid_at' => now(),
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('payments.index', ['search' => 'Specific Customer']));

    $response->assertStatus(200);
    $payments = $response->viewData('page')['props']['payments']['data'];
    expect($payments)->toHaveCount(1);
    expect($payments[0]['id'])->toBe($payment->id);
});

// ============================================
// Payment Methods - Cash, Bank Transfer, Cheque
// ============================================

test('can record cash payment without extra fields', function () {
    $customer = Customer::factory()->create();

    $response = $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => 'App\\Models\\Customer',
        'amount' => 100,
        'payment_method' => 'cash',
        'notes' => 'Cash payment',
    ]);

    $response->assertRedirect(route('payments.index'));
    $this->assertDatabaseHas('payments', [
        'amount' => 100,
        'payment_method' => 'cash',
        'payable_id' => $customer->id,
    ]);
});

test('can record bank transfer with bank name and receipt', function () {
    Storage::fake('public');
    Storage::fake('local');
    $customer = Customer::factory()->create();

    // Simulate FilePond async upload
    $receipt = UploadedFile::fake()->image('receipt.jpg');
    $tempFilename = 'temp-receipt.jpg';
    Storage::disk('local')->putFileAs('tmp', $receipt, $tempFilename);

    $response = $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => 'App\\Models\\Customer',
        'amount' => 250,
        'payment_method' => 'bank_transfer',
        'bank_name' => 'Palestinian Bank',
        'receipt' => $tempFilename,
    ]);

    $response->assertRedirect(route('payments.index'));

    $payment = Payment::where('amount', 250)->first();
    expect($payment->metadata)->toHaveKey('bank_name', 'Palestinian Bank');
    expect($payment->receipt_path)->not->toBeNull();

    Storage::disk('public')->assertExists($payment->receipt_path);
    Storage::disk('local')->assertMissing('tmp/'.$tempFilename);
});

test('can record cheque payment and automated cheque creation', function () {
    $customer = Customer::factory()->create();

    $response = $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => 'App\\Models\\Customer',
        'amount' => 500,
        'payment_method' => 'cheque',
        'cheque_bank_id' => $this->bank->id,
        'cheque_number' => 'CHQ-123',
        'cheque_due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertRedirect(route('payments.index'));

    $this->assertDatabaseHas('payments', [
        'amount' => 500,
        'payment_method' => 'cheque',
    ]);

    $this->assertDatabaseHas('cheques', [
        'amount' => 500,
        'bank_id' => $this->bank->id,
        'reference_number' => 'CHQ-123',
        'chequeable_id' => $customer->id,
        'chequeable_type' => get_class($customer),
    ]);
});

test('can record cheque payment with a new bank name', function () {
    $customer = Customer::factory()->create();

    $response = $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => 'App\\Models\\Customer',
        'amount' => 750,
        'payment_method' => 'cheque',
        'cheque_bank_id' => 'New National Bank',
        'cheque_number' => 'CHQ-NEW-001',
        'cheque_due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertRedirect(route('payments.index'));

    $this->assertDatabaseHas('banks', ['name' => 'New National Bank']);

    $bank = Bank::where('name', 'New National Bank')->first();
    $this->assertDatabaseHas('cheques', [
        'amount' => 750,
        'bank_id' => $bank->id,
        'reference_number' => 'CHQ-NEW-001',
    ]);
});

test('validation fails for bank transfer without bank name', function () {
    $customer = Customer::factory()->create();

    $response = $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => 'App\\Models\\Customer',
        'amount' => 100,
        'payment_method' => 'bank_transfer',
    ]);

    $response->assertSessionHasErrors(['bank_name']);
});

test('validation fails for cheque without cheque details', function () {
    $customer = Customer::factory()->create();

    $response = $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => 'App\\Models\\Customer',
        'amount' => 100,
        'payment_method' => 'cheque',
    ]);

    $response->assertSessionHasErrors(['cheque_bank_id', 'cheque_number', 'cheque_due_date']);
});

// ============================================
// Direct Payments (without invoice)
// ============================================

test('can record direct payment for customer', function () {
    $customer = Customer::factory()->create();

    $response = $this->post(route('payments.store'), [
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 500,
        'payment_method' => PaymentMethod::Cash->value,
        'reference' => 'Direct Payment',
        'notes' => 'No invoice linked',
    ]);

    $response->assertRedirect(route('payments.index'));
    $this->assertDatabaseHas('payments', [
        'payable_id' => $customer->id,
        'payable_type' => Customer::class,
        'amount' => 500,
        'invoice_id' => null,
    ]);

    expect($customer->fresh()->account_balance)->toBe(-500.0);
});

test('can record direct payment for supplier', function () {
    $supplier = Supplier::factory()->create();

    $response = $this->post(route('payments.store'), [
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'amount' => 1000,
        'payment_method' => PaymentMethod::BankTransfer->value,
        'bank_name' => 'Test Bank',
        'reference' => 'Advance Payment',
        'notes' => 'For future purchases',
    ]);

    $response->assertRedirect(route('payments.index'));
    $this->assertDatabaseHas('payments', [
        'payable_id' => $supplier->id,
        'payable_type' => Supplier::class,
        'amount' => 1000,
        'invoice_id' => null,
    ]);

    expect($supplier->fresh()->account_balance)->toBe(-1000.0);
});

test('payment requires either invoice_id or payable_id and type', function () {
    $response = $this->post(route('payments.store'), [
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash->value,
    ]);

    $response->assertSessionHasErrors(['invoice_id', 'payable_id']);
});
