<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// ============================================
// Import Sample Templates
// ============================================

test('can download product import sample template', function () {
    $response = $this->get(route('products.import-sample'));

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
    Customer::factory()->count(3)->create();

    $response = $this->get(route('customers.export'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('Content-Disposition', 'attachment; filename=customers.xlsx');
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
    Supplier::factory()->count(3)->create();

    $response = $this->get(route('suppliers.export'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('Content-Disposition', 'attachment; filename=suppliers.xlsx');
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
    $this->assertDatabaseHas('products', ['name' => 'Imported Product', 'cost' => 250]);

    $product = Product::where('name', 'Imported Product')->first();
    expect($product->units)->toHaveCount(1);
    expect($product->units->first()->name)->toBe('Bag');
    expect($product->units->first()->conversion_factor)->toBe(5);

    expect($product->categories)->toHaveCount(2);
    expect($product->categories->contains('name', 'Cat1'))->toBeTrue();
    expect($product->categories->contains('name', 'Cat2'))->toBeTrue();
});

test('can export products to Excel', function () {
    Product::factory()->count(3)->create();

    $response = $this->get(route('products.export'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('Content-Disposition', 'attachment; filename=products.xlsx');
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
        'cost' => '123.45',
    ]);

    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});
