<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can record an expense', function () {
    $user = User::factory()->create();
    $category = Category::create(['name' => 'Utilities']);

    $response = $this->actingAs($user)
        ->post(route('expenses.store'), [
            'title' => 'Monthly Rent',
            'amount' => 1200.50,
            'expensed_at' => now()->format('Y-m-d'),
            'category_ids' => [$category->id],
            'notes' => 'Rent for April',
        ]);

    $response->assertRedirect(route('expenses.index'));
    $this->assertDatabaseHas('expenses', [
        'title' => 'Monthly Rent',
        'amount' => 1200.50,
    ]);

    $expense = Expense::first();
    expect($expense->categories)->toHaveCount(1);
    expect($expense->categories->first()->name)->toBe('Utilities');
});

test('user can view expenses list', function () {
    $user = User::factory()->create();
    Expense::factory()->count(3)->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)
        ->get(route('expenses.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Expenses/Index')
        ->has('expenses.data', 3)
    );
});
