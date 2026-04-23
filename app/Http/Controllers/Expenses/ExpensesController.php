<?php

namespace App\Http\Controllers\Expenses;

use App\Actions\StoreExpense;
use App\Actions\UpdateExpense;
use App\Exports\ExpenseExport;
use App\Filters\ExpenseFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Queries\ExpenseIndexQuery;
use Maatwebsite\Excel\Facades\Excel;

class ExpensesController extends Controller
{
    public function index(ExpenseFilter $filter, ExpenseIndexQuery $query)
    {
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
        return inertia('Expenses/Create', [
            'categories' => Category::ofType('expense')->get(),
        ]);
    }

    public function store(ExpenseRequest $request, StoreExpense $action)
    {
        $action->handle($request);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded successfully');
    }

    public function show(Expense $expense)
    {
        return inertia('Expenses/Show', [
            'expense' => $expense->load(['categories', 'createdBy']),
        ]);
    }

    public function edit(Expense $expense)
    {
        return inertia('Expenses/Edit', [
            'expense' => $expense->load('categories'),
            'categories' => Category::ofType('expense')->get(),
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense, UpdateExpense $action)
    {
        $action->handle($request, $expense);

        return redirect()->route('expenses.index')->with('success', 'Expense updated successfully');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return back()->with('success', 'Expense deleted successfully');
    }

    public function export(ExpenseFilter $filter)
    {
        return Excel::download(new ExpenseExport($filter), 'expenses.xlsx');
    }
}
