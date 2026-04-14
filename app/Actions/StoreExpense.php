<?php

namespace App\Actions;

use App\Http\Requests\ExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\RecurringExpense;
use Illuminate\Support\Str;

class StoreExpense
{
    public function handle(ExpenseRequest $request): Expense
    {
        $expense = Expense::create([
            'title' => $request->title,
            'amount' => $request->amount,
            'expensed_at' => $request->expensed_at,
            'notes' => $request->notes,
            'receipt_path' => $this->storeReceipt($request),
        ]);

        $this->syncCategories($expense, $request->category_ids ?? []);

        if ($request->boolean('is_recurring')) {
            $this->createRecurringTemplate($expense, $request);
        }

        return $expense;
    }

    private function storeReceipt(ExpenseRequest $request): ?string
    {
        if (! $request->hasFile('receipt')) {
            return null;
        }

        $extension = $request->file('receipt')->getClientOriginalExtension();

        return $request->file('receipt')->storeAs('receipts', Str::uuid().'.'.$extension, 'local');
    }

    private function syncCategories(Expense $expense, array $categoryIds): void
    {
        $expense->categories()->sync($categoryIds);

        if (! empty($categoryIds)) {
            Category::whereIn('id', $categoryIds)->update(['type' => 'expense']);
        }
    }

    private function createRecurringTemplate(Expense $expense, ExpenseRequest $request): void
    {
        $template = RecurringExpense::create([
            'title' => $request->title,
            'amount' => $request->amount,
            'currency' => $expense->currency,
            'notes' => $request->notes,
            'frequency' => $request->frequency,
            'starts_at' => $request->starts_at,
            'ends_at' => $request->ends_at,
            'last_generated_at' => now(),
        ]);

        $template->categories()->sync($request->category_ids ?? []);

        $expense->update(['recurring_expense_id' => $template->id]);
    }
}
