<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

test('can filter customers by category', function () {
    $category = Category::factory()->create(['name' => 'VIP']);
    $vipCustomer = Customer::factory()->create(['name' => 'VIP Customer']);
    $vipCustomer->categories()->attach($category);

    $regularCustomer = Customer::factory()->create(['name' => 'Regular Customer']);

    $response = get(route('customers.index', ['category' => $category->id]));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('customers', 1)
        ->where('customers.0.name', 'VIP Customer')
    );
});

test('can sort customers by name', function () {
    Customer::factory()->create(['name' => 'Zebra']);
    Customer::factory()->create(['name' => 'Apple']);

    $response = get(route('customers.index', ['sort_by' => 'name', 'sort_order' => 'asc']));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->where('customers.0.name', 'Apple')
        ->where('customers.1.name', 'Zebra')
    );
});

test('can filter suppliers by category', function () {
    $category = Category::factory()->create(['name' => 'Main']);
    $mainSupplier = Supplier::factory()->create(['name' => 'Main Supplier']);
    $mainSupplier->categories()->attach($category);

    $otherSupplier = Supplier::factory()->create(['name' => 'Other Supplier']);

    $response = get(route('suppliers.index', ['category' => $category->id]));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('suppliers', 1)
        ->where('suppliers.0.name', 'Main Supplier')
    );
});

test('can sort storages by name', function () {
    Storage::factory()->create(['name' => 'Warehouse B']);
    Storage::factory()->create(['name' => 'Warehouse A']);

    $response = get(route('storages.index', ['sort_by' => 'name', 'sort_order' => 'asc']));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->where('storages.0.name', 'Warehouse A')
        ->where('storages.1.name', 'Warehouse B')
    );
});
