<?php

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('expenses index page loads successfully', function () {
    $user = User::factory()->create();
    Expense::factory()->count(5)->create();

    $response = $this->actingAs($user)->get(route('expenses.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Expenses/Index')
        ->has('expenses.data', 5)
    );
});

test('expenses create page loads successfully', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('expenses.create'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Expenses/Create')
        ->has('categories')
    );
});

test('expenses edit page loads successfully', function () {
    $user = User::factory()->create();
    $expense = Expense::factory()->create();

    $response = $this->actingAs($user)->get(route('expenses.edit', $expense));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Expenses/Edit')
        ->has('expense')
        ->has('categories')
    );
});
