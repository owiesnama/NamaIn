<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_product_with_categories()
    {
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
        $this->assertCount(2, $product->categories);
    }

    public function test_can_create_product_with_new_categories()
    {
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
        $this->assertCount(1, $product->categories);
        $this->assertEquals('New Category', $product->categories->first()->name);
    }

    public function test_can_update_product_categories()
    {
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
        $this->assertCount(1, $product->fresh()->categories);
        $this->assertEquals($categories->first()->id, $product->fresh()->categories->first()->id);
    }

    public function test_can_filter_products_by_category()
    {
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
        $this->assertCount(1, $products);
        $this->assertEquals('Product A', $products[0]['name']);

        // Filter by Category B
        $response = $this->actingAs($user)->get(route('products.index', ['category' => $categoryB->id]));

        $response->assertStatus(200);
        $products = $response->viewData('page')['props']['products']['data'];
        $this->assertCount(1, $products);
        $this->assertEquals('Product B', $products[0]['name']);
    }

    public function test_can_filter_products_by_cost_range()
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Product 10', 'cost' => 10]);
        Product::factory()->create(['name' => 'Product 50', 'cost' => 50]);
        Product::factory()->create(['name' => 'Product 100', 'cost' => 100]);

        $response = $this->actingAs($user)->get(route('products.index', ['min_cost' => 40, 'max_cost' => 60]));

        $response->assertStatus(200);
        $products = $response->viewData('page')['props']['products']['data'];
        $this->assertCount(1, $products);
        $this->assertEquals('Product 50', $products[0]['name']);
    }

    public function test_can_filter_products_by_expiration_date_range()
    {
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
        $this->assertCount(1, $products);
        $this->assertEquals('Product Today', $products[0]['name']);
    }

    public function test_can_sort_products()
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'B Product', 'cost' => 100]);
        Product::factory()->create(['name' => 'A Product', 'cost' => 200]);

        // Sort by name ASC
        $response = $this->actingAs($user)->get(route('products.index', ['sort_by' => 'name', 'sort_order' => 'asc']));
        $products = $response->viewData('page')['props']['products']['data'];
        $this->assertEquals('A Product', $products[0]['name']);

        // Sort by cost DESC
        $response = $this->actingAs($user)->get(route('products.index', ['sort_by' => 'cost', 'sort_order' => 'desc']));
        $products = $response->viewData('page')['props']['products']['data'];
        $this->assertEquals('A Product', $products[0]['name']);
    }
}
