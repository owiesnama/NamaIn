<?php

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\PaymentMethod;
use App\Enums\TreasuryAccountType;
use App\Enums\TreasuryMovementReason;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Bank-transfer POS sales historically recorded a Payment but no treasury
     * movement, so treasury balances under-report bank revenue. Backfill the
     * missing movements against each tenant's configured (or oldest) bank
     * account. Idempotent: payments that already produced a movement are skipped.
     */
    public function up(): void
    {
        $action = app(RecordTreasuryMovementAction::class);
        $original = app()->bound('currentTenant') ? app('currentTenant') : null;

        try {
            Tenant::query()->each(function (Tenant $tenant) use ($action) {
                app()->instance('currentTenant', $tenant);

                $target = $this->targetBankAccount();

                if (! $target) {
                    Log::warning('POS bank-transfer backfill skipped: no bank account', ['tenant_id' => $tenant->id]);

                    return;
                }

                Payment::query()
                    ->where('payment_method', PaymentMethod::BankTransfer->value)
                    ->whereHas('invoice', fn ($invoices) => $invoices->whereNotNull('pos_session_id'))
                    ->with('treasuryMovements')
                    ->orderBy('paid_at')
                    ->each(function (Payment $payment) use ($action, $target) {
                        if ($payment->treasuryMovements->isNotEmpty()) {
                            return;
                        }

                        $account = $payment->treasury_account_id
                            ? TreasuryAccount::find($payment->treasury_account_id) ?? $target
                            : $target;

                        $actor = $this->actorFor($payment);

                        if (! $actor) {
                            Log::warning('POS bank-transfer backfill skipped: no actor', ['payment_id' => $payment->id]);

                            return;
                        }

                        $action->handle(
                            account: $account,
                            amount: Money::fromMajor((float) $payment->amount)->minor(),
                            reason: TreasuryMovementReason::PaymentReceived,
                            movable: $payment,
                            actor: $actor,
                            notes: 'Backfilled POS bank transfer',
                            occurredAt: $payment->paid_at,
                        );

                        $payment->update(['treasury_account_id' => $account->id]);
                    });
            });
        } finally {
            // Restore whatever tenant context (if any) was bound before the loop
            // so callers — including migration-runner tests — keep their binding.
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

    private function targetBankAccount(): ?TreasuryAccount
    {
        $configuredId = preference('pos_default_bank_account_id');

        if ($configuredId && $account = TreasuryAccount::active()->find($configuredId)) {
            return $account;
        }

        return TreasuryAccount::active()
            ->ofType(TreasuryAccountType::Bank)
            ->oldest('id')
            ->first();
    }

    private function actorFor(Payment $payment): ?User
    {
        if ($payment->created_by) {
            return User::withoutGlobalScopes()->find($payment->created_by);
        }

        return User::withoutGlobalScopes()
            ->whereHas('tenants', fn ($tenants) => $tenants->where('tenants.id', app('currentTenant')->id))
            ->first();
    }
};
