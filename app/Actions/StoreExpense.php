<?php

namespace App\Actions;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\TreasuryMovementReason;
use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Models\TreasuryAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreExpense
{
    public function __construct(
        private SyncCategoriesAction $syncCategories,
        private RecordTreasuryMovementAction $recordMovement,
    ) {}

    /**
     * @param  array{title: string, amount: float, expensed_at: string, notes: ?string, receipt_path: ?string, treasury_account_id: ?int, category_objects: ?array, is_recurring: ?bool, frequency: ?string, starts_at: ?string, ends_at: ?string, category_ids: ?array}  $data
     */
    public function handle(array $data, User $actor): Expense
    {
        return DB::transaction(function () use ($data, $actor) {
            $expense = Expense::create([
                'title' => $data['title'],
                'amount' => $data['amount'],
                'expensed_at' => $data['expensed_at'],
                'notes' => $data['notes'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null,
                'treasury_account_id' => $data['treasury_account_id'] ?? null,
            ]);

            if (! empty($data['treasury_account_id'])) {
                $account = TreasuryAccount::findOrFail($data['treasury_account_id']);
                $amountInCents = (int) round($data['amount'] * 100);

                $this->recordMovement->handle(
                    account: $account,
                    amount: -$amountInCents,
                    reason: TreasuryMovementReason::ExpensePaid,
                    movable: $expense,
                    actor: $actor,
                );
            }

            $this->syncCategories->handle($expense, $data['category_objects'] ?? [], 'expense');

            if (! empty($data['is_recurring'])) {
                $this->createRecurringTemplate($expense, $data);
            }

            return $expense;
        });
    }

    private function createRecurringTemplate(Expense $expense, array $data): void
    {
        $template = RecurringExpense::create([
            'title' => $data['title'],
            'amount' => $data['amount'],
            'currency' => $expense->currency,
            'notes' => $data['notes'] ?? null,
            'frequency' => $data['frequency'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'last_generated_at' => now(),
        ]);

        $template->categories()->sync($data['category_ids'] ?? []);

        $expense->update(['recurring_expense_id' => $template->id]);
    }
}
