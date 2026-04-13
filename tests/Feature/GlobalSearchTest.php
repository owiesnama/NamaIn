<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_globally()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Super Widget']);
        $customer = Customer::factory()->create(['name' => 'John Doe', 'phone_number' => '123456']);
        $supplier = Supplier::factory()->create(['name' => 'Jane Smith', 'phone_number' => '654321']);

        // Search for product
        $response = $this->actingAs($user)->get(route('global-search', ['search' => 'Super']));
        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals('Super Widget', $response->json()[0]['name']);
        $this->assertEquals('Product', $response->json()[0]['type']);

        // Search for customer
        $response = $this->actingAs($user)->get(route('global-search', ['search' => 'John']));
        $this->assertCount(1, $response->json());
        $this->assertEquals('John Doe', $response->json()[0]['name']);
        $this->assertEquals('Customer', $response->json()[0]['type']);

        // Search for supplier
        $response = $this->actingAs($user)->get(route('global-search', ['search' => 'Jane']));
        $this->assertCount(1, $response->json());
        $this->assertEquals('Jane Smith', $response->json()[0]['name']);
        $this->assertEquals('Supplier', $response->json()[0]['type']);
    }

    public function test_returns_empty_on_no_search_term()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('global-search'));
        $response->assertStatus(200);
        $this->assertCount(0, $response->json());
    }
}
