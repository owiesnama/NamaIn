<?php

use App\Enums\ExpenseStatus;
use App\Models\Category;
use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('a due recurring expense generates a new expense', function () {
    $admin = User::factory()->admin()->create();
    $template = RecurringExpense::factory()->create([
        'frequency' => 'daily',
        'starts_at' => now()->subDay(),
        'last_generated_at' => null,
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    Artisan::call('expenses:generate-recurring');

    expect(Expense::count())->toBe(1);
    $expense = Expense::first();
    expect($expense->title)->toBe($template->title)
        ->and($expense->amount)->toBe($template->amount)
        ->and($expense->status)->toBe(ExpenseStatus::Pending)
        ->and($expense->recurring_expense_id)->toBe($template->id);
});

test('generated expense inherits categories from template', function () {
    $admin = User::factory()->admin()->create();
    $template = RecurringExpense::factory()->create([
        'frequency' => 'daily',
        'starts_at' => now()->subDay(),
        'is_active' => true,
    ]);

    $category = Category::factory()->create(['type' => 'expense']);
    $template->categories()->attach($category);

    Artisan::call('expenses:generate-recurring');

    $expense = Expense::first();
    expect($expense->categories)->toHaveCount(1)
        ->and($expense->categories->first()->id)->toBe($category->id);
});

test('a non-due recurring expense does not generate', function () {
    RecurringExpense::factory()->create([
        'frequency' => 'daily',
        'starts_at' => now()->addDay(),
        'is_active' => true,
    ]);

    Artisan::call('expenses:generate-recurring');

    expect(Expense::count())->toBe(0);
});

test('a paused recurring expense does not generate', function () {
    RecurringExpense::factory()->create([
        'frequency' => 'daily',
        'starts_at' => now()->subDay(),
        'is_active' => false,
    ]);

    Artisan::call('expenses:generate-recurring');

    expect(Expense::count())->toBe(0);
});

test('a recurring expense past ends_at does not generate', function () {
    RecurringExpense::factory()->create([
        'frequency' => 'daily',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subDay(),
        'is_active' => true,
    ]);

    Artisan::call('expenses:generate-recurring');

    expect(Expense::count())->toBe(0);
});

test('it can create a recurring expense template', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['type' => 'expense']);

    $response = $this->actingAs($user)->post(route('recurring-expenses.store'), [
        'title' => 'Office Rent',
        'amount' => 1200.50,
        'currency' => 'USD',
        'frequency' => 'monthly',
        'starts_at' => now()->format('Y-m-d'),
        'category_ids' => [$category->id],
        'notes' => 'Monthly rent payment',
    ]);

    $response->assertRedirect(route('recurring-expenses.index'));
    $this->assertDatabaseHas('recurring_expenses', [
        'title' => 'Office Rent',
        'amount' => 1200.50,
        'created_by' => $user->id,
    ]);

    $template = RecurringExpense::where('title', 'Office Rent')->first();
    expect($template->categories)->toHaveCount(1);
});

test('it can update a recurring expense template', function () {
    $user = User::factory()->create();
    $template = RecurringExpense::factory()->create(['created_by' => $user->id]);
    $category = Category::factory()->create(['type' => 'expense']);

    $response = $this->actingAs($user)->put(route('recurring-expenses.update', $template->id), [
        'title' => 'Updated Title',
        'amount' => 1500,
        'currency' => 'EUR',
        'frequency' => 'yearly',
        'starts_at' => now()->format('Y-m-d'),
        'category_ids' => [$category->id],
    ]);

    $response->assertRedirect(route('recurring-expenses.index'));
    $this->assertDatabaseHas('recurring_expenses', [
        'id' => $template->id,
        'title' => 'Updated Title',
        'amount' => 1500,
    ]);
});
