<?php

use App\Enums\ExpenseStatus;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('filter by date range returns correct expenses', function () {
    $admin = User::factory()->admin()->create();
    $oldExpense = Expense::factory()->create(['expensed_at' => '2023-01-01']);
    $newExpense = Expense::factory()->create(['expensed_at' => '2023-02-01']);

    $this->actingAs($admin);

    $response = $this->get(route('expenses.index', [
        'from_date' => '2023-01-15',
        'to_date' => '2023-02-15',
    ]));

    $response->assertInertia(fn ($page) => $page
        ->has('expenses.data', 1)
        ->where('expenses.data.0.id', $newExpense->id)
    );
});

test('filter by category returns matching expenses', function () {
    $admin = User::factory()->admin()->create();
    $category1 = Category::factory()->create(['type' => 'expense']);
    $category2 = Category::factory()->create(['type' => 'expense']);

    $expense1 = Expense::factory()->create();
    $expense1->categories()->attach($category1);

    $expense2 = Expense::factory()->create();
    $expense2->categories()->attach($category2);

    $this->actingAs($admin);

    $response = $this->get(route('expenses.index', ['category' => $category1->id]));

    $response->assertInertia(fn ($page) => $page
        ->has('expenses.data', 1)
        ->where('expenses.data.0.id', $expense1->id)
    );
});

test('filter by status returns matching expenses', function () {
    $admin = User::factory()->admin()->create();
    $approved = Expense::factory()->create(['status' => ExpenseStatus::Approved]);
    $pending = Expense::factory()->create(['status' => ExpenseStatus::Pending]);

    $this->actingAs($admin);

    $response = $this->get(route('expenses.index', ['status' => 'approved']));

    $response->assertInertia(fn ($page) => $page
        ->has('expenses.data', 1)
        ->where('expenses.data.0.id', $approved->id)
    );
});

test('filter by amount range returns correct expenses', function () {
    $admin = User::factory()->admin()->create();
    $small = Expense::factory()->create(['amount' => 10]);
    $medium = Expense::factory()->create(['amount' => 50]);
    $large = Expense::factory()->create(['amount' => 100]);

    $this->actingAs($admin);

    $response = $this->get(route('expenses.index', [
        'min_amount' => 40,
        'max_amount' => 60,
    ]));

    $response->assertInertia(fn ($page) => $page
        ->has('expenses.data', 1)
        ->where('expenses.data.0.id', $medium->id)
    );
});
