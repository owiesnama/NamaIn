<?php

namespace App\Actions;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Traits\HandlesAsyncUploads;

class UpdateExpense
{
    use HandlesAsyncUploads;

    public function __construct(private SyncCategoriesAction $syncCategories) {}

    public function handle(ExpenseRequest $request, Expense $expense): Expense
    {
        $expense->update([
            'title' => $request->title,
            'amount' => $request->amount,
            'expensed_at' => $request->expensed_at,
            'notes' => $request->notes,
            'receipt_path' => $this->replaceReceipt($request, $expense),
        ]);

        $this->syncCategories->handle($expense, $request->category_objects ?? [], 'expense');

        return $expense;
    }

    private function replaceReceipt(ExpenseRequest $request, Expense $expense): ?string
    {
        return $this->resolveTemporaryUpload($request->receipt, 'receipts', $expense->receipt_path);
    }
}
