<?php

namespace App\Actions\Reconciliation;

use App\Actions\RecordPaymentAction;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\ResolutionKind;
use App\Models\ChangeLog;
use App\Models\CreditBreachFlag;
use App\Models\ReconciliationItem;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Resolves a credit-breach item (Design 04 §2.2, R5). Money always flows through
 * the existing {@see RecordPaymentAction} or a plain customer-limit edit — never
 * a bespoke write. Collect auto-closes only when the recomputed balance falls to
 * or below the limit; otherwise the payment is recorded and the item stays open.
 *
 * @phpstan-type BreachParams array{amount?: float|int, payment_method?: string, treasury_account_id?: int, credit_limit?: float|int, note?: string}
 */
class ResolveCreditBreachAction
{
    public function __construct(private RecordPaymentAction $recordPayment) {}

    /**
     * @param  BreachParams  $params
     */
    public function handle(ReconciliationItem $item, ResolutionKind $resolution, User $actor, array $params = []): void
    {
        /** @var CreditBreachFlag $subject */
        $subject = $item->subject;

        DB::transaction(function () use ($item, $resolution, $actor, $params, $subject): void {
            ChangeLog::lockTenant($subject->tenant_id);

            match ($resolution) {
                ResolutionKind::Acknowledge => $item->resolveWith($resolution, $actor, $params['note'] ?? null),
                ResolutionKind::Collect => $this->collect($item, $subject, $actor, $params),
                ResolutionKind::RaiseLimit => $this->raiseLimit($item, $subject, $actor, $params),
                default => throw new InvalidArgumentException('Unsupported credit-breach resolution: '.$resolution->value),
            };
        });
    }

    /**
     * @param  BreachParams  $params
     */
    private function collect(ReconciliationItem $item, CreditBreachFlag $subject, User $actor, array $params): void
    {
        $customer = $subject->customer;

        $this->recordPayment->handle(
            invoice: $subject->invoice,
            payable: $customer,
            amount: (float) ($params['amount'] ?? 0),
            method: PaymentMethod::from($params['payment_method'] ?? PaymentMethod::Cash->value),
            direction: PaymentDirection::In,
            options: array_filter(['treasury_account_id' => $params['treasury_account_id'] ?? null]),
        );

        $balanceAfter = Money::fromMajor($customer->fresh()->calculateAccountBalance())->minor();

        if ($balanceAfter <= (int) $subject->credit_limit) {
            $item->resolveWith(ResolutionKind::Collect, $actor, $params['note'] ?? null);
        }
    }

    /**
     * @param  BreachParams  $params
     */
    private function raiseLimit(ReconciliationItem $item, CreditBreachFlag $subject, User $actor, array $params): void
    {
        if (! isset($params['credit_limit'])) {
            throw new InvalidArgumentException('A new credit limit is required.');
        }

        $subject->customer->update(['credit_limit' => $params['credit_limit']]);

        $item->resolveWith(ResolutionKind::RaiseLimit, $actor, $params['note'] ?? null);
    }
}
