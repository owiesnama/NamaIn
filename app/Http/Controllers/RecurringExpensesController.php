<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecurringExpenseRequest;
use App\Models\Category;
use App\Models\RecurringExpense;

class RecurringExpensesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('Expenses/RecurringIndex', [
            'recurring_expenses' => RecurringExpense::with(['categories', 'createdBy'])
                ->withCount('expenses')
                ->latest()
                ->paginate(10),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('Expenses/RecurringCreate', [
            'categories' => Category::ofType('expense')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RecurringExpenseRequest $request)
    {
        $validated = $request->validated();

        $recurringExpense = RecurringExpense::create(array_merge($validated, [
            'created_by' => auth()->id(),
        ]));

        if ($request->category_ids) {
            $recurringExpense->categories()->sync($request->category_ids);
        }

        return redirect()
            ->route('recurring-expenses.index')
            ->with('success', 'Recurring expense template created successfully');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RecurringExpense $recurringExpense)
    {
        $recurringExpense->load('categories');

        return inertia('Expenses/RecurringEdit', [
            'recurring_expense' => $recurringExpense,
            'categories' => Category::ofType('expense')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RecurringExpenseRequest $request, RecurringExpense $recurringExpense)
    {
        $validated = $request->validated();

        $recurringExpense->update($validated);

        $recurringExpense->categories()->sync($request->category_ids ?? []);

        return redirect()
            ->route('recurring-expenses.index')
            ->with('success', 'Recurring expense template updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RecurringExpense $recurringExpense)
    {
        $recurringExpense->delete();

        return redirect()
            ->route('recurring-expenses.index')
            ->with('success', 'Recurring expense template deleted successfully');
    }

    /**
     * Toggle the active status of the recurring expense.
     */
    public function toggle(RecurringExpense $recurringExpense)
    {
        $recurringExpense->update([
            'is_active' => ! $recurringExpense->is_active,
        ]);

        return back()->with('success', 'Recurring expense '.($recurringExpense->is_active ? 'activated' : 'paused').' successfully');
    }
}
