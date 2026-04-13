<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpensesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('Expenses/Index', [
            'expenses' => Expense::latest()
                ->with(['categories', 'createdBy'])
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('Expenses/Create', [
            'categories' => Category::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expensed_at' => 'required|date',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $expense = Expense::create([
            'title' => $request->title,
            'amount' => $request->amount,
            'expensed_at' => $request->expensed_at,
            'notes' => $request->notes,
        ]);

        if ($request->category_ids) {
            $expense->categories()->sync($request->category_ids);
        }

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense recorded successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        $expense->load(['categories', 'createdBy']);

        return inertia('Expenses/Show', [
            'expense' => $expense,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expense $expense)
    {
        $expense->load('categories');

        return inertia('Expenses/Edit', [
            'expense' => $expense,
            'categories' => Category::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expensed_at' => 'required|date',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $expense->update([
            'title' => $request->title,
            'amount' => $request->amount,
            'expensed_at' => $request->expensed_at,
            'notes' => $request->notes,
        ]);

        $expense->categories()->sync($request->category_ids ?? []);

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()
            ->route('expenses.index')
            ->with('success', 'Expense deleted successfully');
    }
}
