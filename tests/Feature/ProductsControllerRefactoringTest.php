<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('can view product details with filters and stats', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create();
    $product = Product::factory()->create();

    // Create a sales transaction
    $saleInvoice = Invoice::factory()->create(['invocable_id' => Customer::factory()->create()->id, 'invocable_type' => Customer::class]);
    Transaction::factory()->create([
        'product_id' => $product->id,
        'invoice_id' => $saleInvoice->id,
        'storage_id' => $storage->id,
        'base_quantity' => 10,
        'quantity' => 10,
    ]);

    // Create a purchase transaction
    $purchaseInvoice = Invoice::factory()->create(['invocable_id' => 1, 'invocable_type' => 'App\Models\Supplier']); // Supplier might not have factory, using manual type
    Transaction::factory()->create([
        'product_id' => $product->id,
        'invoice_id' => $purchaseInvoice->id,
        'storage_id' => $storage->id,
        'base_quantity' => 20,
        'quantity' => 20,
    ]);

    $response = $this->actingAs($user)->get(route('products.show', $product));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Products/Show')
        ->has('product')
        ->has('transactions.data', 2)
        ->where('stats.sales_count', 10)
        ->where('stats.purchases_count', 20)
        ->has('chart_data')
        ->has('insights')
    );

    // Test filtering by type
    $response = $this->actingAs($user)->get(route('products.show', [$product, 'type' => 'Sales']));
    $response->assertInertia(fn (Assert $page) => $page
        ->has('transactions.data', 1)
        ->where('stats.sales_count', 10)
        ->where('stats.purchases_count', 0)
    );
});

test('product insights return correct warnings', function () {
    $product = Product::factory()->create(['alert_quantity' => 10]);
    $storage = Storage::factory()->create();

    // Out of stock
    expect($product->getInsights())->toContain(['type' => 'danger', 'message' => __('Out of Stock')]);

    // Low stock
    $product->stock()->attach($storage->id, ['quantity' => 5, 'public_id' => strtolower((string) Str::ulid())]);
    $product->refresh();
    $insights = $product->getInsights();
    $messages = collect($insights)->pluck('message');
    expect($messages)->toContain(__('Low stock alert: :units units remaining', ['units' => number_format(5, 2)]));

    // Overcommitted
    // Create a pending sale
    $saleInvoice = Invoice::factory()->create(['invocable_id' => Customer::factory()->create()->id, 'invocable_type' => Customer::class]);
    Transaction::factory()->create([
        'product_id' => $product->id,
        'invoice_id' => $saleInvoice->id,
        'storage_id' => $storage->id,
        'delivered' => false,
        'base_quantity' => 20,
    ]);
    $product->refresh();

    $insights = $product->getInsights();
    $messages = collect($insights)->pluck('message');

    expect($messages->first(fn ($m) => str_contains($m, 'Product overcommitted') || str_contains($m, 'التزام زائد للمنتج')))->not->toBeNull();
});

test('products index payload includes cost for each product', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Costed Product', 'cost' => 750]);

    $response = $this->actingAs($user)->get(route('products.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Products/Index')
        ->has('products.data', 1)
        ->where('products.data.0.cost', 750)
    );
});

test('products index payload exposes the global favourite flag for the toggle', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Baseline Product', 'is_global_favorite' => true]);

    $response = $this->actingAs($user)->get(route('products.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Products/Index')
        ->where('products.data.0.is_global_favorite', true)
    );
});

test('can sort products by cost', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Cheap Product', 'cost' => 100]);
    Product::factory()->create(['name' => 'Expensive Product', 'cost' => 900]);

    $response = $this->actingAs($user)->get(route('products.index', ['sort_by' => 'cost', 'sort_order' => 'desc']));

    $products = $response->viewData('page')['props']['products']['data'];
    expect($products[0]['name'])->toBe('Expensive Product');
});

test('currency round-trips when creating a product', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('products.index'), [
        'name' => 'Currency Create Product',
        'cost' => 100,
        'price' => 120,
        'currency' => 'USD',
        'units' => [['name' => 'Box', 'conversion_factor' => 1]],
        'categories' => [],
    ])->assertRedirect(route('products.index'));

    expect(Product::where('name', 'Currency Create Product')->first()->currency)->toBe('USD');
});

test('currency round-trips when updating a product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['currency' => 'SDG']);

    $this->actingAs($user)->put(route('products.update', $product), [
        'name' => $product->name,
        'cost' => $product->cost,
        'price' => $product->price,
        'currency' => 'EUR',
        'units' => [['name' => 'Box', 'conversion_factor' => 1]],
        'categories' => [],
    ])->assertSessionHasNoErrors();

    expect($product->fresh()->currency)->toBe('EUR');
});

test('can download product import sample', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('products.import.sample'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Disposition', 'attachment; filename=products_import_sample.csv');
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    $rows = explode("\n", trim($content));
    $headers = str_getcsv($rows[0]);
    $data = str_getcsv($rows[1]);

    expect($headers)->toBe(['name', 'cost', 'price', 'currency', 'expire_date', 'unit_name', 'unit_conversion_factor', 'categories']);
    expect($data)->toBe(['Example Product', '100', '120', 'SDG', '2026-12-31', 'Box', '10', 'Category1,Category2']);
});
