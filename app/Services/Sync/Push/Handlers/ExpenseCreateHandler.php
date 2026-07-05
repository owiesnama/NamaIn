<?php

namespace App\Services\Sync\Push\Handlers;

use App\Actions\StoreExpenseAction;
use App\Exceptions\Sync\RejectedMutation;
use App\Models\Category;
use App\Models\Device;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\Services\Sync\PublicIdResolver;
use App\Services\Sync\Push\MutationHandler;
use App\Services\Sync\Push\PushMutation;
use App\ValueObjects\Money;

/**
 * `expense.create` (Design 03 §5.3): categories referenced by public_id
 * (pull-only, never minted here), a mandatory drawer, and an optional
 * `receipt_public_id` the separate attachment upload links back to. Runs the
 * existing StoreExpenseAction, so the expense lands `pending` by DB default and
 * enters the approval queue with zero approval-specific sync code.
 */
class ExpenseCreateHandler implements MutationHandler
{
    public function __construct(
        private StoreExpenseAction $storeExpense,
        private PublicIdResolver $resolver,
    ) {}

    public function handle(PushMutation $mutation, User $actor, Device $device): array
    {
        $payload = $mutation->payload;

        $treasuryAccountId = $this->resolver->id(TreasuryAccount::class, $payload['treasury_account'] ?? null);

        if ($treasuryAccountId === null) {
            throw RejectedMutation::unknownReference(__('Unknown treasury account for expense.'));
        }

        $expense = $this->storeExpense->handle([
            'public_id' => $mutation->publicId,
            'receipt_public_id' => $payload['receipt_public_id'] ?? null,
            'title' => $payload['title'],
            'amount' => Money::fromMinor((int) $payload['amount'])->major(),
            'expensed_at' => $payload['expensed_at'],
            'notes' => $payload['notes'] ?? null,
            'treasury_account_id' => $treasuryAccountId,
            'category_objects' => $this->resolveCategories($payload['categories'] ?? []),
        ], $actor);

        return ['public_id' => $expense->public_id, 'serial' => null];
    }

    /**
     * Resolve pulled category public_ids to the shape SyncCategoriesAction
     * expects (`id` finds the existing row — never mints a new one).
     *
     * @param  list<string>  $publicIds
     * @return list<array{id: int, name: string}>
     */
    private function resolveCategories(array $publicIds): array
    {
        $categories = [];

        foreach ($publicIds as $publicId) {
            $category = Category::where('public_id', $publicId)->first();

            if ($category === null) {
                throw RejectedMutation::unknownReference(__('Unknown expense category.'));
            }

            $categories[] = ['id' => $category->id, 'name' => $category->name];
        }

        return $categories;
    }
}
