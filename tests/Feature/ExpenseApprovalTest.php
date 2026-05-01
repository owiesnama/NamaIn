<?php

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('approving an expense sets status, approved_by, and approved_at', function () {
    $admin = User::factory()->admin()->create();
    $expense = Expense::factory()->create(['status' => ExpenseStatus::Pending]);

    $response = $this->actingAs($admin)
        ->put(route('expenses.approval', $expense), [
            'status' => 'approved',
        ]);

    $response->assertRedirect();
    $expense->refresh();

    expect($expense->status)->toBe(ExpenseStatus::Approved)
        ->and($expense->approved_by)->toBe($admin->id)
        ->and($expense->approved_at)->not->toBeNull();
});

test('rejecting an expense sets status to rejected', function () {
    $admin = User::factory()->admin()->create();
    $expense = Expense::factory()->create(['status' => ExpenseStatus::Pending]);

    $response = $this->actingAs($admin)
        ->put(route('expenses.approval', $expense), [
            'status' => 'rejected',
        ]);

    $response->assertRedirect();
    $expense->refresh();

    expect($expense->status)->toBe(ExpenseStatus::Rejected)
        ->and($expense->approved_by)->toBe($admin->id)
        ->and($expense->approved_at)->not->toBeNull();
});

test('approved expenses count in expenses_this_month', function () {
    $admin = User::factory()->admin()->create();
    Expense::factory()->create([
        'amount' => 100,
        'status' => ExpenseStatus::Approved,
        'expensed_at' => now(),
    ]);

    $this->actingAs($admin);

    // We need to call the private method or check the dashboard response
    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('expenses_this_month', 100)
    );
});

test('pending expenses do not count in expenses_this_month', function () {
    $admin = User::factory()->admin()->create();
    Expense::factory()->create([
        'amount' => 100,
        'status' => ExpenseStatus::Pending,
        'expensed_at' => now(),
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('expenses_this_month', 0)
    );
});
