<?php

namespace App\Console\Commands;

use App\Actions\Treasury\RecordTreasuryMovementAction;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\TreasuryMovementReason;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TreasuryAccount;
use App\ValueObjects\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackfillCashPaymentTreasury extends Command
{
    protected $signature = 'treasury:backfill-cash-payments {--dry-run : Report what would change without writing}';

    protected $description = 'Attach orphaned cash payments to the default cash account and record their missing treasury movements';

    public function handle(RecordTreasuryMovementAction $recordMovement): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $backfilled = 0;
        $skipped = 0;

        foreach (Tenant::withoutGlobalScopes()->get() as $tenant) {
            app()->instance('currentTenant', $tenant);

            $defaultCash = TreasuryAccount::defaultCash();

            $orphans = Payment::whereNull('treasury_account_id')
                ->where('payment_method', PaymentMethod::Cash)
                ->whereDoesntHave('treasuryMovements')
                ->oldest('paid_at')
                ->get();

            if ($orphans->isEmpty()) {
                continue;
            }

            if (! $defaultCash) {
                $this->warn("Tenant [{$tenant->slug}]: {$orphans->count()} orphaned cash payment(s) but no active shared cash account — skipped.");
                $skipped += $orphans->count();

                continue;
            }

            foreach ($orphans as $payment) {
                if ($dryRun) {
                    $this->line("Would attach payment #{$payment->id} ({$payment->amount}) to [{$defaultCash->name}] for tenant [{$tenant->slug}].");
                    $backfilled++;

                    continue;
                }

                $amount = Money::fromMajor($payment->amount);

                $recordMovement->handle(
                    account: $defaultCash,
                    amount: $payment->direction === PaymentDirection::In ? $amount->minor() : $amount->negate()->minor(),
                    reason: $payment->direction === PaymentDirection::In
                        ? TreasuryMovementReason::PaymentReceived
                        : TreasuryMovementReason::ExpensePaid,
                    movable: $payment,
                    actor: $payment->createdBy ?? $tenant->owner(),
                    notes: 'Backfilled from orphaned cash payment',
                    occurredAt: Carbon::parse($payment->paid_at),
                );

                $payment->update(['treasury_account_id' => $defaultCash->id]);
                $backfilled++;
            }
        }

        app()->forgetInstance('currentTenant');

        $verb = $dryRun ? 'Would backfill' : 'Backfilled';
        $this->info("{$verb} {$backfilled} cash payment(s); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
