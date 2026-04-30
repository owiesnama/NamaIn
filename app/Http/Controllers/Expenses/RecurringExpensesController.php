<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecurringExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\RecurringExpense;

class RecurringExpensesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Expense::class);

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
        $this->authorize('create', Expense::class);

        return inertia('Expenses/RecurringCreate', [
            'categories' => Category::ofType('expense')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RecurringExpenseRequest $request)
    {
        $this->authorize('create', Expense::class);
        $data = $request->safe()->except('category_ids');
        $data['created_by'] = auth()->id();

        $recurringExpense = RecurringExpense::create($data);

        if ($request->category_ids) {
            $recurringExpense->categories()->sync($request->category_ids);
        }

        return redirect()
            ->route('recurring-expenses.index')
            ->with('success', __('Recurring expense template created successfully'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RecurringExpense $recurringExpense)
    {
        $this->authorize('create', Expense::class);
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
        $this->authorize('create', Expense::class);
        $recurringExpense->update($request->safe()->except('category_ids'));

        $recurringExpense->categories()->sync($request->category_ids ?? []);

        return redirect()
            ->route('recurring-expenses.index')
            ->with('success', __('Recurring expense template updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RecurringExpense $recurringExpense)
    {
        $this->authorize('delete', Expense::class);
        $recurringExpense->delete();

        return redirect()
            ->route('recurring-expenses.index')
            ->with('success', __('Recurring expense template deleted successfully'));
    }
}
