<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('only_auth_users_can_see_customers_page', function () {
    /** @var TestCase $this */
    $this->get('/customers')
        ->assertRedirect();
    $user = User::factory()->create();
    $this->be($user)
        ->get('/customers')
        ->assertOk();
});

test('auth_users_can_create_a_new_customers', function () {
    /** @var TestCase $this */
    $customerAttributes = [
        'name' => 'Fake Customer',
        'phone' => '012341234',
    ];
    $this->post('/customers', $customerAttributes)->assertRedirect();
    $user = User::factory()->create();
    $this->be($user)
        ->post('/customers', $customerAttributes)
        ->assertRedirect()
        ->assertSessionHas('flash', [
            'title' => 'Customer Created 🎉',
            'message' => 'Customer created successfully',
        ]);
});
