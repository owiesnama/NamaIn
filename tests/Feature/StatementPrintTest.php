<?php

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it renders the customer statement print page', function () {
    $customer = Customer::factory()->create();

    $response = $this->get(route('customers.print-statement', $customer));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Customers/PrintStatement')
        ->has('customer')
        ->has('start_date')
        ->has('end_date')
        ->has('opening_balance')
        ->has('closing_balance')
        ->has('activities')
        ->has('qr_url')
    );
});

test('it renders the supplier statement print page', function () {
    $supplier = Supplier::factory()->create();

    $response = $this->get(route('suppliers.print-statement', $supplier));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Suppliers/PrintStatement')
        ->has('supplier')
        ->has('start_date')
        ->has('end_date')
        ->has('opening_balance')
        ->has('closing_balance')
        ->has('activities')
        ->has('qr_url')
    );
});
