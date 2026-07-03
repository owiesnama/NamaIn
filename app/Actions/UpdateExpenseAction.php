<?php

namespace App\Actions;

use App\Models\ChangeLog;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class UpdateExpenseAction
{
    public function __construct(private SyncCategoriesAction $syncCategories) {}

    /**
     * @param  array{title: string, amount: float, expensed_at: string, notes: ?string, receipt_path: ?string, category_objects: ?array}  $data
     */
    public function handle(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            ChangeLog::lockTenant($expense->tenant_id);

            $expense->update([
                'title' => $data['title'],
                'amount' => $data['amount'],
                'expensed_at' => $data['expensed_at'],
                'notes' => $data['notes'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? $expense->receipt_path,
            ]);

            $this->syncCategories->handle($expense, $data['category_objects'] ?? [], 'expense');

            return $expense;
        });
    }
}
