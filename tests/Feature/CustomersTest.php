<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Auth Users Can See Customers Page', function () {
    $this->get(route('customers.index'))
        ->assertRedirect();
    $user = User::factory()->create();
    $this->be($user)
        ->get(route('customers.index'))
        ->assertOk();
});

test('Auth Users Can Create A New Customers', function () {
    $customerAttributes = [
        'name' => 'Fake Customer',
        'phone_number' => '0654623',
        'address' => 'fake address',
    ];
    $user = User::factory()->create();

    $response = $this->be($user)
        ->post(route('customers.store'), $customerAttributes);

    $response->assertRedirect();

    $this->assertDatabaseHas(Customer::class, $customerAttributes);
});

test('Only admins can delete customers', function () {
    $customer = Customer::factory()->create();

    $this->signIn($customer)
        ->delete(route('customers.destroy', $customer));

    $this->assertSoftDeleted(Customer::class, [
        'id' => $customer->fresh()->id,
    ]);
});
