<?php

namespace App\Actions;

use App\Http\Requests\ExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateExpense
{
    public function handle(ExpenseRequest $request, Expense $expense): Expense
    {
        $expense->update([
            'title' => $request->title,
            'amount' => $request->amount,
            'expensed_at' => $request->expensed_at,
            'notes' => $request->notes,
            'receipt_path' => $this->replaceReceipt($request, $expense),
        ]);

        $this->syncCategories($expense, $request->category_ids ?? []);

        return $expense;
    }

    private function replaceReceipt(ExpenseRequest $request, Expense $expense): ?string
    {
        if (! $request->hasFile('receipt')) {
            return $expense->receipt_path;
        }

        if ($expense->receipt_path) {
            Storage::disk('local')->delete($expense->receipt_path);
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
}
