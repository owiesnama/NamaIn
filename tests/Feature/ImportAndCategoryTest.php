<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportAndCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_download_product_import_sample()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('products.import-sample'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_can_import_customers()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('customers.csv');

        // We use a real file content for CSV import test since it's simple
        $content = "name,address,phone_number,credit_limit\n";
        $content .= "Imported Customer,Address 123,0123456789,1000\n";

        $filePath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($filePath, $content);
        $uploadedFile = new UploadedFile($filePath, 'customers.csv', 'text/csv', null, true);

        $response = $this->actingAs($user)->post(route('customers.import'), [
            'file' => $uploadedFile,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('customers', ['name' => 'Imported Customer']);
    }

    public function test_can_import_suppliers()
    {
        $user = User::factory()->create();

        $content = "name,address,phone_number\n";
        $content .= "Imported Supplier,Supplier Address,9876543210\n";

        $filePath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($filePath, $content);
        $uploadedFile = new UploadedFile($filePath, 'suppliers.csv', 'text/csv', null, true);

        $response = $this->actingAs($user)->post(route('suppliers.import'), [
            'file' => $uploadedFile,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('suppliers', ['name' => 'Imported Supplier']);
    }

    public function test_can_create_customer_with_categories()
    {
        $user = User::factory()->create();
        $categories = Category::factory()->count(2)->create();

        $response = $this->actingAs($user)->post(route('customers.index'), [
            'name' => 'Customer with Categories',
            'address' => 'Test Address long enough',
            'phone_number' => '0123456789',
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->toArray(),
        ]);

        $response->assertRedirect(route('customers.index'));
        $customer = Customer::where('name', 'Customer with Categories')->first();
        $this->assertCount(2, $customer->categories);
    }

    public function test_can_create_supplier_with_categories()
    {
        $user = User::factory()->create();
        $categories = Category::factory()->count(2)->create();

        $response = $this->actingAs($user)->post(route('suppliers.index'), [
            'name' => 'Supplier with Categories',
            'address' => 'Test Address long enough',
            'phone_number' => '0123456789',
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->toArray(),
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $supplier = Supplier::where('name', 'Supplier with Categories')->first();
        $this->assertCount(2, $supplier->categories);
    }

    public function test_can_import_products_with_units_and_categories()
    {
        $user = User::factory()->create();

        $content = "name,cost,expire_date,unit_name,unit_conversion_factor,categories\n";
        $content .= "Imported Product,250,2026-10-10,Bag,5,\"Cat1,Cat2\"\n";

        $filePath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($filePath, $content);
        $uploadedFile = new UploadedFile($filePath, 'products.csv', 'text/csv', null, true);

        $response = $this->actingAs($user)->post(route('products.import'), [
            'file' => $uploadedFile,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('products', ['name' => 'Imported Product', 'cost' => 250]);

        $product = Product::where('name', 'Imported Product')->first();
        $this->assertCount(1, $product->units);
        $this->assertEquals('Bag', $product->units->first()->name);
        $this->assertEquals(5, $product->units->first()->conversion_factor);

        $this->assertCount(2, $product->categories);
        $this->assertTrue($product->categories->contains('name', 'Cat1'));
        $this->assertTrue($product->categories->contains('name', 'Cat2'));
    }
}
