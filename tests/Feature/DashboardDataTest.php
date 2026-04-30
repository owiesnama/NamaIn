<?php

use App\Enums\ChequeStatus;
use App\Enums\ExpenseStatus;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard displays enriched data', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create(['name' => 'Test Product']);
    $customer = Customer::factory()->create(['name' => 'Test Customer']);
    $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);

    // Create a sale to show up in top products
    $invoice = Invoice::create([
        'invocable_id' => $customer->id,
        'invocable_type' => Customer::class,
        'total' => 1000,
        'serial_number' => 'INV-001',
        'status' => 'delivered',
    ]);

    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $invoice->id,
        'quantity' => 10,
        'base_quantity' => 10,
        'price' => 100,
        'delivered' => 1,
        'created_at' => now(),
    ]);

    // Create a purchase
    $purchaseInvoice = Invoice::create([
        'invocable_id' => $supplier->id,
        'invocable_type' => Supplier::class,
        'total' => 500,
        'discount' => 0,
        'paid_amount' => 100,
        'serial_number' => 'PUR-001',
        'status' => 'delivered',
    ]);

    Transaction::create([
        'product_id' => $product->id,
        'storage_id' => $storage->id,
        'invoice_id' => $purchaseInvoice->id,
        'quantity' => 5,
        'base_quantity' => 5,
        'price' => 100,
        'delivered' => 1,
        'created_at' => now(),
    ]);

    // Create an expense
    Expense::create([
        'title' => 'Office Rent',
        'amount' => 200,
        'expensed_at' => now(),
        'created_by' => $user->id,
        'status' => ExpenseStatus::Approved,
    ]);

    // Create a cheque due soon
    Cheque::create([
        'chequeable_id' => $customer->id,
        'chequeable_type' => Customer::class,
        'amount' => 500,
        'type' => 1, // Debit
        'due' => now()->addDays(5),
        'bank' => 'Test Bank',
        'reference_number' => 'CHQ-123',
        'status' => ChequeStatus::Issued,
    ]);

    // Create a low stock product
    $lowStockProduct = Product::factory()->create(['name' => 'Low Stock Item', 'cost' => 50, 'alert_quantity' => 100]);
    $lowStockProduct->stock()->attach($storage->id, ['quantity' => 10]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertStatus(200);
    $data = $response->viewData('page')['props'];

    // Assertions for new stat cards
    $this->assertEquals(200, $data['expenses_this_month']);
    $this->assertEquals(400, $data['outstanding_payables']); // 500 total - 100 paid
    $this->assertEquals(1000 - 0 - 200, $data['gross_profit']); // revenue - cogs - expenses (unit_cost is null so cogs = 0)

    // Assertions for top products and customers
    $this->assertCount(1, $data['top_products']);
    $this->assertEquals('Test Product', $data['top_products'][0]['product']['name']);

    $this->assertCount(1, $data['top_customers']);
    $this->assertEquals('Test Customer', $data['top_customers'][0]['name']);
    $this->assertEquals(1000, $data['top_customers'][0]['revenue']);

    // Assertions for alerts
    $lowStockNames = collect($data['low_stock_products'])->pluck('name');
    $this->assertTrue($lowStockNames->contains('Low Stock Item'));

    $this->assertCount(1, $data['upcoming_cheques']);
    $this->assertEquals('Test Customer', $data['upcoming_cheques'][0]['payee']['name']);

    // Check recent expenses
    $this->assertCount(1, $data['recent_expenses']);
    $this->assertEquals('Office Rent', $data['recent_expenses'][0]['title']);

    // Check monthly stats (chart data)
    $this->assertArrayHasKey('expenses', $data['monthly_stats']);
    $this->assertEquals(200, end($data['monthly_stats']['expenses']));

    // Check transactions
    $this->assertNotEmpty($data['transactions']);
    $this->assertIsString($data['transactions'][0]['created_at']);
});
