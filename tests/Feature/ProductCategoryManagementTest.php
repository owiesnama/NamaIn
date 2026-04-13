<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================
// Product Creation with Categories
// ============================================

test('can create product with existing categories', function () {
    $user = User::factory()->create();
    $categories = Category::factory()->count(2)->create();

    $response = $this->actingAs($user)->post(route('products.index'), [
        'name' => 'New Product',
        'cost' => 100,
        'expire_date' => now()->addYear()->format('Y-m-d'),
        'units' => [
            ['name' => 'Box', 'conversion_factor' => 1],
        ],
        'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->toArray(),
    ]);

    $response->assertRedirect(route('products.index'));
    $this->assertDatabaseHas('products', ['name' => 'New Product']);

    $product = Product::where('name', 'New Product')->first();
    expect($product->categories)->toHaveCount(2);
});

test('can create product with new categories', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('products.index'), [
        'name' => 'New Product with New Cat',
        'cost' => 100,
        'expire_date' => now()->addYear()->format('Y-m-d'),
        'units' => [
            ['name' => 'Box', 'conversion_factor' => 1],
        ],
        'categories' => [
            ['id' => 'New Category', 'name' => 'New Category'],
        ],
    ]);

    $response->assertRedirect(route('products.index'));
    $this->assertDatabaseHas('categories', ['name' => 'New Category']);

    $product = Product::where('name', 'New Product with New Cat')->first();
    expect($product->categories)->toHaveCount(1);
    expect($product->categories->first()->name)->toBe('New Category');
});

// ============================================
// Product Updates with Categories
// ============================================

test('can update product categories', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $categories = Category::factory()->count(2)->create();

    $response = $this->actingAs($user)->put(route('products.update', $product), [
        'name' => $product->name,
        'cost' => $product->cost,
        'expire_date' => $product->expire_date,
        'units' => [
            ['name' => 'Box', 'conversion_factor' => 1],
        ],
        'categories' => [['id' => $categories->first()->id, 'name' => $categories->first()->name]],
    ]);

    $response->assertSessionHasNoErrors();
    expect($product->fresh()->categories)->toHaveCount(1);
    expect($product->fresh()->categories->first()->id)->toBe($categories->first()->id);
});

test('can update product adding new category', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->put(route('products.update', $product), [
        'name' => $product->name,
        'cost' => $product->cost,
        'expire_date' => $product->expire_date,
        'units' => [
            ['name' => 'Box', 'conversion_factor' => 1],
        ],
        'categories' => [
            ['id' => 'Brand New Cat', 'name' => 'Brand New Cat'],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('categories', ['name' => 'Brand New Cat']);
    expect($product->fresh()->categories)->toHaveCount(1);
    expect($product->fresh()->categories->first()->name)->toBe('Brand New Cat');
});

// ============================================
// Product Filtering
// ============================================

test('can filter products by category', function () {
    $user = User::factory()->create();
    $categoryA = Category::factory()->create(['name' => 'Category A']);
    $categoryB = Category::factory()->create(['name' => 'Category B']);

    $productA = Product::factory()->create(['name' => 'Product A']);
    $productA->categories()->attach($categoryA);

    $productB = Product::factory()->create(['name' => 'Product B']);
    $productB->categories()->attach($categoryB);

    // Filter by Category A
    $response = $this->actingAs($user)->get(route('products.index', ['category' => $categoryA->id]));

    $response->assertStatus(200);
    $products = $response->viewData('page')['props']['products']['data'];
    expect($products)->toHaveCount(1);
    expect($products[0]['name'])->toBe('Product A');

    // Filter by Category B
    $response = $this->actingAs($user)->get(route('products.index', ['category' => $categoryB->id]));

    $response->assertStatus(200);
    $products = $response->viewData('page')['props']['products']['data'];
    expect($products)->toHaveCount(1);
    expect($products[0]['name'])->toBe('Product B');
});

test('can filter products by cost range', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Product 10', 'cost' => 10]);
    Product::factory()->create(['name' => 'Product 50', 'cost' => 50]);
    Product::factory()->create(['name' => 'Product 100', 'cost' => 100]);

    $response = $this->actingAs($user)->get(route('products.index', ['min_cost' => 40, 'max_cost' => 60]));

    $response->assertStatus(200);
    $products = $response->viewData('page')['props']['products']['data'];
    expect($products)->toHaveCount(1);
    expect($products[0]['name'])->toBe('Product 50');
});

test('can filter products by expiration date range', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'Product Expired', 'expire_date' => now()->subDay()->format('Y-m-d')]);
    Product::factory()->create(['name' => 'Product Today', 'expire_date' => now()->format('Y-m-d')]);
    Product::factory()->create(['name' => 'Product Future', 'expire_date' => now()->addDay()->format('Y-m-d')]);

    $response = $this->actingAs($user)->get(route('products.index', [
        'expire_from' => now()->format('Y-m-d'),
        'expire_to' => now()->format('Y-m-d'),
    ]));

    $response->assertStatus(200);
    $products = $response->viewData('page')['props']['products']['data'];
    expect($products)->toHaveCount(1);
    expect($products[0]['name'])->toBe('Product Today');
});

// ============================================
// Product Sorting
// ============================================

test('can sort products by name and cost', function () {
    $user = User::factory()->create();
    Product::factory()->create(['name' => 'B Product', 'cost' => 100]);
    Product::factory()->create(['name' => 'A Product', 'cost' => 200]);

    // Sort by name ASC
    $response = $this->actingAs($user)->get(route('products.index', ['sort_by' => 'name', 'sort_order' => 'asc']));
    $products = $response->viewData('page')['props']['products']['data'];
    expect($products[0]['name'])->toBe('A Product');

    // Sort by cost DESC
    $response = $this->actingAs($user)->get(route('products.index', ['sort_by' => 'cost', 'sort_order' => 'desc']));
    $products = $response->viewData('page')['props']['products']['data'];
    expect($products[0]['name'])->toBe('A Product');
});
