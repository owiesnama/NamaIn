<?php

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create customer with opening debit and credit', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('customers.store'), [
        'name' => 'John Doe',
        'address' => '123 Test Street, Test City',
        'phone_number' => '1234567890',
        'opening_debit' => 500.50,
        'opening_credit' => 0,
        'credit_limit' => 1000,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('customers', [
        'name' => 'John Doe',
        'opening_debit' => 500.50,
        'opening_credit' => 0,
    ]);

    $customer = Customer::where('name', 'John Doe')->first();
    // Formula: invoicedTotal - totalPaid + opening_debit - opening_credit
    // 0 - 0 + 500.50 - 0 = 500.50
    expect($customer->calculateAccountBalance())->toBe(500.50);
});

test('can create customer with opening credit', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('customers.store'), [
        'name' => 'Jane Doe',
        'address' => '123 Test Street, Test City',
        'phone_number' => '1234567890',
        'opening_credit' => 300,
        'credit_limit' => 1000,
    ]);

    $response->assertRedirect();

    $customer = Customer::where('name', 'Jane Doe')->first();
    // Formula: 0 - 0 + 0 - 300 = -300
    expect($customer->calculateAccountBalance())->toBe(-300.0);
});

test('cannot update customer opening balance fields', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['opening_debit' => 100]);

    $response = $this->actingAs($user)->put(route('customers.update', $customer), [
        'name' => 'John Updated',
        'address' => '123 Test Street, Test City',
        'phone_number' => '1234567890',
        'opening_debit' => 500.50,
    ]);

    $response->assertSessionHasErrors(['opening_debit']);
});

test('can create supplier with opening debit and credit', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('suppliers.store'), [
        'name' => 'Jane Supplier',
        'address' => '456 Supplier Ave, Test City',
        'phone_number' => '0987654321',
        'opening_credit' => 750.25,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('suppliers', [
        'name' => 'Jane Supplier',
        'opening_credit' => 750.25,
    ]);

    $supplier = Supplier::where('name', 'Jane Supplier')->first();
    // Formula: 0 - 0 + 0 - 750.25 = -750.25
    expect($supplier->calculateAccountBalance())->toBe(-750.25);
});

test('cannot update supplier opening balance fields', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['opening_credit' => 200]);

    $response = $this->actingAs($user)->put(route('suppliers.update', $supplier), [
        'name' => 'Jane Updated',
        'address' => '456 Supplier Ave, Test City',
        'phone_number' => '0987654321',
        'opening_credit' => 800,
    ]);

    $response->assertSessionHasErrors(['opening_credit']);
});
