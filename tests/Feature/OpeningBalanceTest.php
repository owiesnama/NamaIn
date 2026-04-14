<?php

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create customer with opening balance', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('customers.store'), [
        'name' => 'John Doe',
        'address' => '123 Test Street, Test City',
        'phone_number' => '1234567890',
        'opening_balance' => 500.50,
        'credit_limit' => 1000,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('customers', [
        'name' => 'John Doe',
        'opening_balance' => 500.50,
    ]);

    $customer = Customer::where('name', 'John Doe')->first();
    expect($customer->calculateAccountBalance())->toBe(500.50);
});

test('cannot update customer opening balance', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['opening_balance' => 100]);

    $response = $this->actingAs($user)->put(route('customers.update', $customer), [
        'name' => 'John Updated',
        'address' => '123 Test Street, Test City',
        'phone_number' => '1234567890',
        'opening_balance' => 500.50, // Should be ignored/prohibited
    ]);

    $response->assertSessionHasErrors(['opening_balance']);

    $customer->refresh();
    expect($customer->opening_balance)->toBe('100.00');
});

test('can create supplier with opening balance', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('suppliers.store'), [
        'name' => 'Jane Supplier',
        'address' => '456 Supplier Ave, Test City',
        'phone_number' => '0987654321',
        'opening_balance' => 750.25,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('suppliers', [
        'name' => 'Jane Supplier',
        'opening_balance' => 750.25,
    ]);

    $supplier = Supplier::where('name', 'Jane Supplier')->first();
    expect($supplier->calculateAccountBalance())->toBe(750.25);
});

test('cannot update supplier opening balance', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['opening_balance' => 200]);

    $response = $this->actingAs($user)->put(route('suppliers.update', $supplier), [
        'name' => 'Jane Updated',
        'address' => '456 Supplier Ave, Test City',
        'phone_number' => '0987654321',
        'opening_balance' => 800, // Should be ignored/prohibited
    ]);

    $response->assertSessionHasErrors(['opening_balance']);

    $supplier->refresh();
    expect($supplier->opening_balance)->toBe('200.00');
});
