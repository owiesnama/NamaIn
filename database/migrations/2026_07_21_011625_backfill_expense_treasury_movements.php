<?php

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\TreasuryMovementReason;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Expenses recorded without a treasury account produced no movement, so the
     * treasury never reflected the outflow. Backfill a negative movement for
     * each such expense, against its own account or the tenant's default cash
     * account. Idempotent: expenses that already produced a movement are skipped.
     */
    public function up(): void
    {
        $action = app(RecordTreasuryMovementAction::class);
        $original = app()->bound('currentTenant') ? app('currentTenant') : null;

        try {
            Tenant::query()->each(function (Tenant $tenant) use ($action) {
                app()->instance('currentTenant', $tenant);

                $defaultCash = TreasuryAccount::defaultCash();

                Expense::query()
                    ->with('treasuryMovements')
                    ->orderBy('expensed_at')
                    ->each(function (Expense $expense) use ($action, $defaultCash) {
                        if ($expense->treasuryMovements->isNotEmpty()) {
                            return;
                        }

                        $account = $expense->treasury_account_id
                            ? TreasuryAccount::find($expense->treasury_account_id) ?? $defaultCash
                            : $defaultCash;

                        if (! $account) {
                            return;
                        }

                        $actor = $this->actorFor($expense);

                        if (! $actor) {
                            Log::warning('Expense treasury backfill skipped: no actor', ['expense_id' => $expense->id]);

                            return;
                        }

                        $action->handle(
                            account: $account,
                            amount: -Money::fromMajor((float) $expense->amount)->minor(),
                            reason: TreasuryMovementReason::ExpensePaid,
                            movable: $expense,
                            actor: $actor,
                            notes: 'Backfilled expense',
                            occurredAt: $expense->expensed_at,
                        );

                        if (! $expense->treasury_account_id) {
                            $expense->update(['treasury_account_id' => $account->id]);
                        }
                    });
            });
        } finally {
            if ($original) {
                app()->instance('currentTenant', $original);
            } else {
                app()->forgetInstance('currentTenant');
            }
        }
    }

    public function down(): void
    {
        // Backfilled movements are not reversible without data loss; no-op.
    }

    private function actorFor(Expense $expense): ?User
    {
        if ($expense->created_by) {
            return User::withoutGlobalScopes()->find($expense->created_by);
        }

        return User::withoutGlobalScopes()
            ->whereHas('tenants', fn ($tenants) => $tenants->where('tenants.id', app('currentTenant')->id))
            ->first();
    }
};
