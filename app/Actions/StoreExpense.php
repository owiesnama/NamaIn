<?php

namespace App\Actions;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\TreasuryMovementReason;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Models\TreasuryAccount;
use App\Traits\HandlesAsyncUploads;

class StoreExpense
{
    use HandlesAsyncUploads;

    public function __construct(
        private SyncCategoriesAction $syncCategories,
        private RecordTreasuryMovementAction $recordMovement,
    ) {}

    public function handle(ExpenseRequest $request): Expense
    {
        $expense = Expense::create([
            'title' => $request->title,
            'amount' => $request->amount,
            'expensed_at' => $request->expensed_at,
            'notes' => $request->notes,
            'receipt_path' => $this->storeReceipt($request),
            'treasury_account_id' => $request->treasury_account_id,
        ]);

        if ($request->treasury_account_id) {
            $account = TreasuryAccount::findOrFail($request->treasury_account_id);
            $amountInCents = (int) round($request->amount * 100);

            $this->recordMovement->handle(
                account: $account,
                amount: -$amountInCents,
                reason: TreasuryMovementReason::ExpensePaid,
                movable: $expense,
                actor: auth()->user(),
            );
        }

        $this->syncCategories->handle($expense, $request->category_objects ?? [], 'expense');

        if ($request->boolean('is_recurring')) {
            $this->createRecurringTemplate($expense, $request);
        }

        return $expense;
    }

    private function storeReceipt(ExpenseRequest $request): ?string
    {
        return $this->resolveTemporaryUpload($request->receipt, 'receipts');
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
