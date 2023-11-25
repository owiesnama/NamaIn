<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('Only Auth Users Can See Customers Page', function () {
    /** @var TestCase $this */
    $this->get(route('customers.index'))
        ->assertRedirect();
    $user = User::factory()->create();
    $this->be($user)
        ->get(route('customers.index'))
        ->assertOk();
});

test('Auth Users Can Create A New Customers', function () {
    /** @var TestCase $this */
    $customerAttributes = [
        'name' => 'Fake Customer',
        'phone_number' => '0654623',
        'address' => 'fake address'
    ];
    $user = User::factory()->create();

    $this->withoutExceptionHandling();
    $response = $this->be($user)
        ->post(route('customers.store'), $customerAttributes);

    $response->assertRedirect();

    $this->assertDatabaseHas(\App\Models\Customer::class, $customerAttributes);
});
