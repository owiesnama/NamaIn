<?php

namespace App\Actions\Pos;

use App\Actions\Reconciliation\RaiseReconciliationItem;
use App\Enums\PaymentMethod;
use App\Enums\ReconciliationType;
use App\Models\CreditBreachFlag;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\OversellReconciliation;
use App\Models\PosSession;
use App\Models\User;
use App\ValueObjects\CheckoutContext;
use App\ValueObjects\Money;
use App\ValueObjects\SaleReplayResult;
use Illuminate\Support\Collection;

/**
 * Replays a pushed `sale.create` (Design 02 §5.4) as one atomic mutation
 * through the shared {@see ProcessPosCheckoutAction}. This action stays thin:
 * the CheckoutContext (device register, AllowNegative stock, no replenishment,
 * the device-minted preset identity) is built by the push handler; here we
 * capture pre-sale on-hand, replay the checkout, and record the two
 * server-derived flags — oversell (§6.1) and credit breach (§6.2) — in the same
 * transaction. InsufficientStockException never fires: AllowNegative
 * force-deducts, so an oversell is recorded, never rejected (FR-13).
 */
class ReplayPosSaleAction
{
    public function __construct(
        private ProcessPosCheckoutAction $processCheckout,
        private RaiseReconciliationItem $raiseReconciliationItem,
    ) {}

    public function handle(PosSession $session, Collection $data, User $actor, CheckoutContext $context, Device $device): SaleReplayResult
    {
        $storage = $session->storage;

        $onHandBefore = collect($data->get('items'))
            ->pluck('product_id')
            ->unique()
            ->mapWithKeys(fn (int $productId): array => [$productId => $storage->quantityOf($productId)]);

        $invoice = $this->processCheckout->handle($session, $data, $actor, null, false, $context);

        $oversell = $this->recordOversell($invoice, $storage->id, $onHandBefore, $device, $context, $actor);
        $creditBreach = $this->recordCreditBreach($invoice, $data, $device, $context, $actor);

        return new SaleReplayResult($invoice, $oversell, $creditBreach);
    }

    /**
     * One oversell row per product whose total sold quantity exceeded the
     * pre-sale on-hand (Design 02 §6.1). Quantities beyond available stock are
     * "oversold"; stock already at or below zero contributes its whole line.
     *
     * @param  Collection<int, int>  $onHandBefore  product_id => on-hand before the sale
     * @return list<array{product: string, oversold_qty: int}>
     */
    private function recordOversell(Invoice $invoice, int $storageId, Collection $onHandBefore, Device $device, CheckoutContext $context, User $actor): array
    {
        $flags = [];

        $invoice->loadMissing('transactions.product');

        foreach ($invoice->transactions->groupBy('product_id') as $productId => $lines) {
            $needed = (int) $lines->sum('base_quantity');
            $before = (int) $onHandBefore->get($productId, 0);
            $oversold = $needed - max($before, 0);

            if ($oversold <= 0) {
                continue;
            }

            $oversellRow = OversellReconciliation::create([
                'tenant_id' => $invoice->tenant_id,
                'device_id' => $device->id,
                'storage_id' => $storageId,
                'product_id' => $productId,
                'invoice_id' => $invoice->id,
                'oversold_qty' => $oversold,
                'on_hand_before' => $before,
                'occurred_at' => now(),
            ]);

            $this->raiseReconciliationItem->for(
                subject: $oversellRow,
                type: ReconciliationType::Oversell,
                device: $device,
                register: $context->register,
                actor: $actor,
                occurredAt: $oversellRow->occurred_at,
            );

            $flags[] = ['product' => $lines->first()->product->public_id, 'oversold_qty' => $oversold];
        }

        return $flags;
    }

    /**
     * Flag a credit sale whose post-sale balance exceeds the customer's cached
     * limit (Design 02 §6.2). Never rejected — the sale is already recorded.
     */
    private function recordCreditBreach(Invoice $invoice, Collection $data, Device $device, CheckoutContext $context, User $actor): bool
    {
        if (($data->get('payment_method') ?? PaymentMethod::Cash->value) !== PaymentMethod::Credit->value) {
            return false;
        }

        $customerId = $data->get('customer_id');

        if (! $customerId) {
            return false;
        }

        $customer = Customer::find($customerId);
        $creditLimit = (int) $customer->getRawOriginal('credit_limit');

        if ($creditLimit <= 0) {
            return false;
        }

        $balanceAfter = Money::fromMajor($customer->calculateAccountBalance())->minor();

        if ($balanceAfter <= $creditLimit) {
            return false;
        }

        $breachRow = CreditBreachFlag::create([
            'tenant_id' => $invoice->tenant_id,
            'device_id' => $device->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'credit_limit' => $creditLimit,
            'balance_after' => $balanceAfter,
            'occurred_at' => now(),
        ]);

        $this->raiseReconciliationItem->for(
            subject: $breachRow,
            type: ReconciliationType::CreditBreach,
            device: $device,
            register: $context->register,
            actor: $actor,
            occurredAt: $breachRow->occurred_at,
        );

        return true;
    }
}
