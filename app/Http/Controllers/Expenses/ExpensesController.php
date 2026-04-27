<?php

namespace App\Http\Controllers\Expenses;

use App\Actions\StoreExpense;
use App\Actions\UpdateExpense;
use App\Filters\ExpenseFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\Queries\ExpenseIndexQuery;

class ExpensesController extends Controller
{
    public function index(ExpenseFilter $filter, ExpenseIndexQuery $query)
    {
        $this->authorize('viewAny', Expense::class);

        return inertia('Expenses/Index', [
            'expenses' => Expense::filter($filter)
                ->with(['categories', 'createdBy'])
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'categories' => Category::ofType('expense')->get(),
            'users' => User::select('id', 'name')->get(),
            'category_budgets' => $query->categoryBudgets(),
            'spending_by_category' => $query->spendingByCategory($filter),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Expense::class);

        return inertia('Expenses/Create', [
            'categories' => Category::ofType('expense')->get(),
            'treasury_accounts' => TreasuryAccount::active()->get()->map(fn (TreasuryAccount $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type_label' => $a->type->label(),
                'current_balance' => $a->currentBalance(),
            ]),
        ]);
    }

    public function store(ExpenseRequest $request, StoreExpense $action)
    {
        $this->authorize('create', Expense::class);

        $action->handle($request);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded successfully');
    }

    public function show(Expense $expense)
    {
        $this->authorize('view', $expense);

        return inertia('Expenses/Show', [
            'expense' => $expense->load(['categories', 'createdBy']),
        ]);
    }

    public function edit(Expense $expense)
    {
        $this->authorize('update', $expense);

        return inertia('Expenses/Edit', [
            'expense' => $expense->load('categories'),
            'categories' => Category::ofType('expense')->get(),
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense, UpdateExpense $action)
    {
        $this->authorize('update', $expense);

        $action->handle($request, $expense);

        return redirect()->route('expenses.index')->with('success', 'Expense updated successfully');
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return back()->with('success', 'Expense deleted successfully');
    }
}
