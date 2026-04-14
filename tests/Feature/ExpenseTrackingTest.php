<?php

use App\Enums\ExpenseStatus;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('user can record an expense with a receipt', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('receipt.pdf', 100);

    $response = $this->actingAs($user)
        ->post(route('expenses.store'), [
            'title' => 'Hardware Purchase',
            'amount' => 500,
            'expensed_at' => now()->format('Y-m-d'),
            'receipt' => $file,
        ]);

    $response->assertRedirect(route('expenses.index'));

    $expense = Expense::first();
    expect($expense->receipt_path)->not->toBeNull();
    Storage::disk('local')->assertExists($expense->receipt_path);
});

test('index response contains budget limit data', function () {
    $user = User::factory()->admin()->create();
    $category = Category::create([
        'name' => 'Marketing',
        'type' => 'expense',
        'budget_limit' => 1000,
    ]);

    Expense::factory()->create([
        'amount' => 200,
        'status' => ExpenseStatus::Approved,
        'expensed_at' => now(),
    ])->categories()->attach($category);

    $response = $this->actingAs($user)
        ->get(route('expenses.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('category_budgets', 1)
        ->where('category_budgets.0.name', 'Marketing')
        ->where('category_budgets.0.limit', '1000.00')
        ->where('category_budgets.0.spent', 200)
    );
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
