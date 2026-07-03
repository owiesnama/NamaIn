<?php

use App\Jobs\GenerateExportJob;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ImportLog;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function createQuickBooksXlsx(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;

    // Sheet 0 - empty (matches real QB file)
    $sheet0 = $spreadsheet->getActiveSheet();
    $sheet0->setTitle('Sheet0');

    // Sheet 1 - customer data
    $sheet1 = $spreadsheet->createSheet(1);
    $sheet1->setTitle('Customers');

    $headers = [
        'B1' => 'Active Status',
        'C1' => 'Customer',
        'D1' => 'Currency',
        'E1' => 'Balance',
        'F1' => 'Balance Total',
        'O1' => 'Phone',
        'T1' => 'Bill to 1',
        'AK1' => 'Credit Limit',
        'AR1' => 'Note',
    ];

    foreach ($headers as $cell => $value) {
        $sheet1->setCellValue($cell, $value);
    }

    foreach ($rows as $i => $row) {
        $rowNum = $i + 2;
        $sheet1->setCellValue("B{$rowNum}", $row['active_status'] ?? 'Active');
        $sheet1->setCellValue("C{$rowNum}", $row['customer'] ?? '');
        $sheet1->setCellValue("D{$rowNum}", $row['currency'] ?? 'USD');
        $sheet1->setCellValue("E{$rowNum}", $row['balance'] ?? 0);
        $sheet1->setCellValue("F{$rowNum}", $row['balance_total'] ?? 0);
        $sheet1->setCellValue("O{$rowNum}", $row['phone'] ?? '');
        $sheet1->setCellValue("T{$rowNum}", $row['bill_to_1'] ?? '');
        $sheet1->setCellValue("AK{$rowNum}", $row['credit_limit'] ?? 0);
    }

    $filePath = tempnam(sys_get_temp_dir(), 'qb').'.xlsx';
    (new Xlsx($spreadsheet))->save($filePath);

    return new UploadedFile($filePath, 'quickbooks.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function createQuickBooksProductXlsx(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;

    $sheet0 = $spreadsheet->getActiveSheet();
    $sheet0->setTitle('Sheet0');

    $sheet1 = $spreadsheet->createSheet(1);
    $sheet1->setTitle('Products');

    $headers = [
        'B1' => 'Active Status',
        'C1' => 'Type',
        'D1' => 'Item',
        'E1' => 'Cost',
        'F1' => 'Price',
        'G1' => 'UM',
    ];

    foreach ($headers as $cell => $value) {
        $sheet1->setCellValue($cell, $value);
    }

    foreach ($rows as $i => $row) {
        $rowNum = $i + 2;
        $sheet1->setCellValue("B{$rowNum}", $row['active_status'] ?? 'Active');
        $sheet1->setCellValue("C{$rowNum}", $row['type'] ?? 'Inventory Part');
        $sheet1->setCellValue("D{$rowNum}", $row['item'] ?? '');
        $sheet1->setCellValue("E{$rowNum}", $row['cost'] ?? 0);
        $sheet1->setCellValue("F{$rowNum}", $row['price'] ?? 0);
        $sheet1->setCellValue("G{$rowNum}", $row['um'] ?? '');
    }

    $filePath = tempnam(sys_get_temp_dir(), 'qb-products').'.xlsx';
    (new Xlsx($spreadsheet))->save($filePath);

    return new UploadedFile($filePath, 'quickbooks-products.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function createProductXlsx(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Products');

    $headers = [
        'A1' => 'name',
        'B1' => 'cost',
        'C1' => 'currency',
        'D1' => 'expire_date',
        'E1' => 'unit_name',
        'F1' => 'unit_conversion_factor',
        'G1' => 'categories',
    ];

    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }

    foreach ($rows as $i => $row) {
        $rowNum = $i + 2;
        $sheet->setCellValue("A{$rowNum}", $row['name'] ?? '');
        $sheet->setCellValue("B{$rowNum}", $row['cost'] ?? '');
        $sheet->setCellValue("C{$rowNum}", $row['currency'] ?? 'SDG');
        $sheet->setCellValue("D{$rowNum}", $row['expire_date'] ?? '');
        $sheet->setCellValue("E{$rowNum}", $row['unit_name'] ?? '');
        $sheet->setCellValue("F{$rowNum}", $row['unit_conversion_factor'] ?? '');
        $sheet->setCellValue("G{$rowNum}", $row['categories'] ?? '');
    }

    $filePath = tempnam(sys_get_temp_dir(), 'products').'.xlsx';
    (new Xlsx($spreadsheet))->save($filePath);

    return new UploadedFile($filePath, 'products.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

// ============================================
// Import Sample Templates
// ============================================

test('can download product import sample template', function () {
    $response = $this->get(route('products.import.sample'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

// ============================================
// Customer Import/Export
// ============================================

test('can import customers from CSV', function () {
    $content = "name,address,phone_number,credit_limit\n";
    $content .= "Imported Customer,Address 123,0123456789,1000\n";

    $filePath = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($filePath, $content);
    $uploadedFile = new UploadedFile($filePath, 'customers.csv', 'text/csv', null, true);

    $response = $this->post(route('customers.import'), [
        'file' => $uploadedFile,
    ]);

    $response->assertStatus(302);
    $this->assertDatabaseHas('customers', ['name' => 'Imported Customer']);
});

test('can export customers to Excel', function () {
    Queue::fake();
    Customer::factory()->count(3)->create();

    $response = $this->get(route('customers.export'));

    $response->assertRedirect();
    Queue::assertPushed(GenerateExportJob::class);
    $this->assertDatabaseHas('export_logs', ['export_key' => 'customers']);
});

test('can create customer with categories', function () {
    $categories = Category::factory()->count(2)->create();

    $response = $this->post(route('customers.index'), [
        'name' => 'Customer with Categories',
        'address' => 'Test Address long enough',
        'phone_number' => '0123456789',
        'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->toArray(),
    ]);

    $response->assertRedirect(route('customers.index'));
    $customer = Customer::where('name', 'Customer with Categories')->first();
    expect($customer->categories)->toHaveCount(2);
});

// ============================================
// Supplier Import/Export
// ============================================

test('can import suppliers from CSV', function () {
    $content = "name,address,phone_number\n";
    $content .= "Imported Supplier,Supplier Address,9876543210\n";

    $filePath = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($filePath, $content);
    $uploadedFile = new UploadedFile($filePath, 'suppliers.csv', 'text/csv', null, true);

    $response = $this->post(route('suppliers.import'), [
        'file' => $uploadedFile,
    ]);

    $response->assertStatus(302);
    $this->assertDatabaseHas('suppliers', ['name' => 'Imported Supplier']);
});

test('can export suppliers to Excel', function () {
    Queue::fake();
    Supplier::factory()->count(3)->create();

    $response = $this->get(route('suppliers.export'));

    $response->assertRedirect();
    Queue::assertPushed(GenerateExportJob::class);
    $this->assertDatabaseHas('export_logs', ['export_key' => 'suppliers']);
});

test('can create supplier with categories', function () {
    $categories = Category::factory()->count(2)->create();

    $response = $this->post(route('suppliers.index'), [
        'name' => 'Supplier with Categories',
        'address' => 'Test Address long enough',
        'phone_number' => '0123456789',
        'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->toArray(),
    ]);

    $response->assertRedirect(route('suppliers.index'));
    $supplier = Supplier::where('name', 'Supplier with Categories')->first();
    expect($supplier->categories)->toHaveCount(2);
});

// ============================================
// Product Import/Export
// ============================================

test('can import products with units and categories from CSV', function () {
    $content = "name,cost,expire_date,unit_name,unit_conversion_factor,categories\n";
    $content .= "Imported Product,250,2026-10-10,Bag,5,\"Cat1,Cat2\"\n";

    $filePath = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($filePath, $content);
    $uploadedFile = new UploadedFile($filePath, 'products.csv', 'text/csv', null, true);

    $response = $this->post(route('products.import'), [
        'file' => $uploadedFile,
    ]);

    $response->assertStatus(302);
    $this->assertDatabaseHas('products', ['name' => 'Imported Product', 'cost' => 25000]);

    $product = Product::where('name', 'Imported Product')->first();
    expect($product->units)->toHaveCount(1);
    expect($product->units->first()->name)->toBe('Bag');
    expect($product->units->first()->conversion_factor)->toBe(5);

    expect($product->categories)->toHaveCount(2);
    expect($product->categories->contains('name', 'Cat1'))->toBeTrue();
    expect($product->categories->contains('name', 'Cat2'))->toBeTrue();
});

test('can import products with day first spreadsheet expiry dates', function () {
    $file = createProductXlsx([
        [
            'name' => 'Day First Product',
            'cost' => 250,
            'expire_date' => '31/12/2027',
            'unit_name' => 'Bag',
            'unit_conversion_factor' => 5,
            'categories' => 'Cat1',
        ],
    ]);

    $response = $this->post(route('products.import'), [
        'file' => $file,
    ]);

    $response->assertStatus(302);

    $product = Product::where('name', 'Day First Product')->first();

    expect($product)->not->toBeNull();
    expect(substr((string) $product->expire_date, 0, 10))->toBe('2027-12-31');
    expect($product->units)->toHaveCount(1);
    expect($product->categories->contains('name', 'Cat1'))->toBeTrue();
});

test('product import expiry date failure message does not require a specific format', function () {
    $file = createProductXlsx([
        [
            'name' => 'Invalid Date Product',
            'cost' => 250,
            'expire_date' => 'not a date',
        ],
    ]);

    $response = $this->post(route('products.import'), [
        'file' => $file,
    ]);

    $response->assertStatus(302);

    $importLog = ImportLog::latest()->first();

    expect($importLog->failures[0]['errors'])->toContain(__('Expiry date must be a valid date.'));
});

test('can export products to Excel', function () {
    Queue::fake();
    Product::factory()->count(3)->create();

    $response = $this->get(route('products.export'));

    $response->assertRedirect();
    Queue::assertPushed(GenerateExportJob::class);
    $this->assertDatabaseHas('export_logs', ['export_key' => 'products']);
});

test('product export-import roundtrip works', function () {
    $productName = 'Roundtrip Product '.uniqid();
    Product::factory()->create([
        'name' => $productName,
        'cost' => 123.45,
        'currency' => 'SDG',
    ]);

    // Test CSV import that matches export format
    $csvContent = "name,cost,currency,expire_date,categories,units\n";
    $csvContent .= "{$productName},123.45,USD,2026-12-31,\"Cat1,Cat2\",\"Box(10)\"";

    $tempFile = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($tempFile, $csvContent);

    $file = new UploadedFile($tempFile, 'products.csv', 'text/csv', null, true);

    $response = $this->post(route('products.import'), [
        'file' => $file,
    ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('products', [
        'name' => $productName,
        'cost' => 12345,
    ]);

    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});

test('can import products from quickbooks excel file', function () {
    $file = createQuickBooksProductXlsx([
        ['item' => 'Food:QB Product One', 'cost' => 120, 'price' => 150, 'um' => 'each (piece)'],
        ['item' => 'QB Product Two', 'cost' => 80, 'price' => 0],
    ]);

    $response = $this->post(route('products.import'), [
        'file' => $file,
        'template' => 'quickbooks',
    ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('products', [
        'name' => 'QB Product One',
        'cost' => 12000,
        'price' => 15000,
    ]);

    $this->assertDatabaseHas('categories', ['name' => 'Food']);
    $this->assertDatabaseHas('units', ['name' => 'each']);

    $this->assertDatabaseHas('products', [
        'name' => 'QB Product Two',
        'cost' => 8000,
        'price' => 8000,
    ]);

    $this->assertDatabaseHas('import_logs', [
        'import_type' => 'products',
        'template' => 'quickbooks',
        'status' => 'completed',
    ]);
});

test('quickbooks product import reports an empty sheet with a translated failure message', function () {
    $file = createQuickBooksProductXlsx([]);

    $response = $this->post(route('products.import'), [
        'file' => $file,
        'template' => 'quickbooks',
    ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('import_logs', [
        'import_type' => 'products',
        'template' => 'quickbooks',
        'status' => 'failed',
        'failure_message' => __('Import failed. The selected file does not contain any rows to import.'),
    ]);
});

// ============================================
// Customer Import - Validation
// ============================================

test('customer import skips rows with missing name and collects failures', function () {
    $content = "name,address,phone_number,credit_limit\n";
    $content .= ",Address 123,0123456789,1000\n";
    $content .= "Valid Customer,Address 456,0987654321,2000\n";

    $filePath = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($filePath, $content);
    $uploadedFile = new UploadedFile($filePath, 'customers.csv', 'text/csv', null, true);

    $this->post(route('customers.import'), ['file' => $uploadedFile]);

    $this->assertDatabaseHas('customers', ['name' => 'Valid Customer']);
    $this->assertDatabaseMissing('customers', ['address' => 'Address 123', 'name' => '']);

    $importLog = ImportLog::latest()->first();
    expect($importLog->failures)->not->toBeEmpty();
    expect($importLog->failures[0]['attribute'])->toBe('name');
});

test('default template works without template parameter', function () {
    $content = "name,address,phone_number,credit_limit\n";
    $content .= "Backward Compat Customer,Address 123,0123456789,1000\n";

    $filePath = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($filePath, $content);
    $uploadedFile = new UploadedFile($filePath, 'customers.csv', 'text/csv', null, true);

    $response = $this->post(route('customers.import'), ['file' => $uploadedFile]);

    $response->assertStatus(302);
    $this->assertDatabaseHas('customers', ['name' => 'Backward Compat Customer']);
    $this->assertDatabaseHas('import_logs', ['template' => 'default']);
});

test('invalid template is rejected', function () {
    $content = "name\nTest\n";
    $filePath = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($filePath, $content);
    $uploadedFile = new UploadedFile($filePath, 'customers.csv', 'text/csv', null, true);

    $response = $this->post(route('customers.import'), [
        'file' => $uploadedFile,
        'template' => 'invalid',
    ]);

    $response->assertSessionHasErrors('template');
});

// ============================================
// QuickBooks Customer Import
// ============================================

test('can import customers from quickbooks excel file', function () {
    $file = createQuickBooksXlsx([
        ['customer' => 'QB Customer One', 'phone' => '0551234567', 'bill_to_1' => 'Khartoum', 'credit_limit' => 5000, 'balance' => 1500],
        ['customer' => 'QB Customer Two', 'phone' => '0559876543', 'bill_to_1' => 'Omdurman', 'credit_limit' => 3000, 'balance' => -200],
    ]);

    $response = $this->post(route('customers.import'), [
        'file' => $file,
        'template' => 'quickbooks',
    ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('customers', [
        'name' => 'QB Customer One',
        'phone_number' => '0551234567',
        'address' => 'Khartoum',
        'credit_limit' => 500000,
        'opening_debit' => 150000,
        'opening_credit' => 0,
    ]);

    $this->assertDatabaseHas('customers', [
        'name' => 'QB Customer Two',
        'opening_debit' => 0,
        'opening_credit' => 20000,
    ]);
});

test('quickbooks import skips inactive customers', function () {
    $file = createQuickBooksXlsx([
        ['active_status' => 'Active', 'customer' => 'Active Customer'],
        ['active_status' => 'Inactive', 'customer' => 'Inactive Customer'],
    ]);

    $this->post(route('customers.import'), [
        'file' => $file,
        'template' => 'quickbooks',
    ]);

    $this->assertDatabaseHas('customers', ['name' => 'Active Customer']);
    $this->assertDatabaseMissing('customers', ['name' => 'Inactive Customer']);
});

test('quickbooks import skips empty or dot-only customer names', function () {
    $file = createQuickBooksXlsx([
        ['customer' => 'Real Customer'],
        ['customer' => '.'],
        ['customer' => '..................'],
        ['customer' => '   '],
        ['customer' => ''],
    ]);

    $this->post(route('customers.import'), [
        'file' => $file,
        'template' => 'quickbooks',
    ]);

    expect(Customer::count())->toBe(1);
    $this->assertDatabaseHas('customers', ['name' => 'Real Customer']);
});

test('quickbooks import stores template on import log', function () {
    $file = createQuickBooksXlsx([
        ['customer' => 'Template Test Customer'],
    ]);

    $this->post(route('customers.import'), [
        'file' => $file,
        'template' => 'quickbooks',
    ]);

    $this->assertDatabaseHas('import_logs', [
        'import_type' => 'customers',
        'template' => 'quickbooks',
    ]);
});
