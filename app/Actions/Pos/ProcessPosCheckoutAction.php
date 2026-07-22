<?php

namespace App\Actions\Pos;

use App\Actions\RecordPaymentAction;
use App\Actions\Stock\DeliverTransactionAction;
use App\Actions\Stock\TransferStockAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TreasuryAccountType;
use App\Exceptions\InsufficientStockException;
use App\Models\ChangeLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Storage;
use App\Models\TreasuryAccount;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\InventoryStrategy;
use App\Services\Pos\DrawerResolver;
use App\ValueObjects\CheckoutContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcessPosCheckoutAction
{
    public function __construct(
        private RecordPaymentAction $recordPayment,
        private DeliverTransactionAction $deliverAction,
        private FindReplenishmentSourceAction $findReplenishmentSource,
        private TransferStockAction $executeStockTransfer,
        private DrawerResolver $drawerResolver,
    ) {}

    public function handle(
        PosSession $session,
        Collection $data,
        User $actor,
        ?string $idempotencyKey = null,
        bool $acknowledgeTransfers = false,
        ?CheckoutContext $context = null
    ): Invoice {
        $context ??= CheckoutContext::cloudWeb($session->tenant_id);

        if (! $session->isOpen()) {
            throw new DomainException('POS session is closed.');
        }

        $paymentMethod = PaymentMethod::tryFrom($data->get('payment_method', 'cash')) ?? PaymentMethod::Cash;

        if ($paymentMethod === PaymentMethod::Credit && ! $data->get('customer_id')) {
            throw new DomainException('Credit sales require a named customer.');
        }

        return DB::transaction(function () use ($session, $data, $actor, $idempotencyKey, $acknowledgeTransfers, $paymentMethod, $context) {
            ChangeLog::lockTenant($session->tenant_id);

            if ($idempotencyKey) {
                $existing = Invoice::where('tenant_id', $session->tenant_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            // 1. Replenish if needed. AllowNegative contexts (push replay, local
            // runtime) and overselling tenants never block on stock: the sale
            // drives the sale-point balance negative and is surfaced for
            // reconciliation instead.
            foreach ($data->get('items') as $item) {
                if ($context->allowsNegativeStock()) {
                    break;
                }

                $unit = isset($item['unit_id']) ? Unit::find($item['unit_id']) : null;
                $quantity = $item['quantity'] * ($unit->conversion_factor ?? 1);

                $available = $session->storage->quantityOf($item['product_id']);
                $needed = $quantity;

                if ($available < $needed && ! app(InventoryStrategy::class)->allowsOverselling()) {
                    if (! $acknowledgeTransfers || ! $context->executeReplenishment) {
                        throw new InsufficientStockException(Product::findOrFail($item['product_id']), $session->storage);
                    }

                    $this->replenish($session->storage, $item['product_id'], $needed - $available, $actor);
                }
            }

            // 2. Resolve Customer (Default to Walk-in if not provided)
            $customerId = $data->get('customer_id');
            $customerType = $data->get('customer_type', Customer::class);

            if (! $customerId && $customerType === Customer::class) {
                // Match the walk-in by the stable is_system flag, not its display
                // name — otherwise a translated name (e.g. Arabic) forks a second
                // walk-in customer that never matches the seeded one.
                $customerId = Customer::firstOrCreate(
                    ['tenant_id' => $session->tenant_id, 'is_system' => true],
                    ['name' => __('Walk-in Customer'), 'address' => 'N/A', 'phone_number' => 'N/A']
                )->id;
            }

            // 3. Create Invoice — numbered on the context's register; a preset
            // identity (push replay) is stored verbatim instead of minted
            $invoice = Invoice::create(array_merge([
                'tenant_id' => $session->tenant_id,
                'pos_session_id' => $session->id,
                'register_id' => $context->register->id,
                'invocable_type' => $customerType,
                'invocable_id' => $customerId,
                'total' => $data->get('total'),
                'payment_method' => $data->get('payment_method', 'cash'),
                'payment_status' => PaymentStatus::Unpaid,
                'paid_amount' => 0,
                'status' => InvoiceStatus::Initial,
                'idempotency_key' => $idempotencyKey,
            ], $context->preset ? [
                'public_id' => $context->preset->invoicePublicId,
                'serial_number' => $context->preset->serialNumber,
            ] : []));

            // 4. Create Transactions & Deduct Stock
            $productIds = collect($data->get('items'))->pluck('product_id')->unique();
            $averageCosts = Product::whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $linePublicIds = $context->preset?->linePublicIds ?? [];

            foreach (array_values($data->get('items')) as $index => $item) {
                $unit = isset($item['unit_id']) ? Unit::find($item['unit_id']) : null;
                $quantity = $item['quantity'] * ($unit->conversion_factor ?? 1);

                $transaction = $invoice->transactions()->create([
                    'tenant_id' => $session->tenant_id,
                    'public_id' => $linePublicIds[$index] ?? null,
                    'product_id' => $item['product_id'],
                    'storage_id' => $session->storage_id,
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'base_quantity' => $quantity,
                    'price' => $item['price'],
                    'unit_cost' => $averageCosts[$item['product_id']]->average_cost ?? 0,
                    'cost_provisional' => ($averageCosts[$item['product_id']]->average_cost ?? 0) <= 0,
                    'delivered' => false,
                ]);

                $this->deliverAction->handle($transaction, $actor, $session->storage, $context->allowsNegativeStock());
            }

            $invoice->markAs(InvoiceStatus::Delivered);

            // 5. Record payment only for methods that collect money at checkout
            if ($this->recordsImmediatePayment($paymentMethod)) {
                $accountId = $this->resolveCheckoutAccountId(
                    $paymentMethod,
                    $session,
                    $context,
                    $data->get('treasury_account_id'),
                );

                $this->recordPayment->handle(
                    invoice: $invoice,
                    payable: $invoice->invocable,
                    amount: $invoice->total,
                    method: $paymentMethod,
                    direction: PaymentDirection::In,
                    options: [
                        'notes' => 'POS sale',
                        'treasury_account_id' => $accountId,
                        'public_id' => $context->preset?->paymentPublicId,
                    ]
                );
            }

            return $invoice;
        });
    }

    private function recordsImmediatePayment(PaymentMethod $method): bool
    {
        return in_array($method, [
            PaymentMethod::Cash,
            PaymentMethod::Cheque,
            PaymentMethod::BankTransfer,
        ], true);
    }

    /**
     * The treasury account id a checkout payment lands in. Cash uses the
     * context register's drawer (register-linked for device registers,
     * sale-point-linked for cloud R0 — see DrawerResolver), falling back to the
     * shared default cash account (and, failing that, null —
     * RecordPaymentAction still routes cash to the default, so cash-only
     * tenants with no account keep working). Bank transfers must land
     * somewhere: the account picked at checkout or the configured POS default,
     * otherwise the sale is blocked so bank revenue never goes unbanked.
     */
    private function resolveCheckoutAccountId(
        PaymentMethod $method,
        PosSession $session,
        CheckoutContext $context,
        int|string|null $requestedAccountId,
    ): ?int {
        if ($method === PaymentMethod::Cash) {
            $drawer = $this->drawerResolver->resolveActive($context->register, $session->storage)
                ?? TreasuryAccount::defaultCash();

            return $drawer?->id;
        }

        $account = $this->resolveBankAccount($requestedAccountId);

        if (! $account) {
            throw new DomainException(__('No treasury account is available for :method payments. Choose an account or set a POS default in settings.', [
                'method' => $method->label(),
            ]));
        }

        return $account->id;
    }

    private function resolveBankAccount(int|string|null $requestedAccountId): ?TreasuryAccount
    {
        if ($requestedAccountId) {
            return TreasuryAccount::active()->find($requestedAccountId);
        }

        $defaultId = preference('pos_default_bank_account_id');

        return $defaultId ? TreasuryAccount::active()->find($defaultId) : null;
    }

    private function replenish(Storage $salePoint, int $productId, int $quantityNeeded, User $actor): void
    {
        $product = Product::findOrFail($productId);

        $source = $this->findReplenishmentSource
            ->handle($product, $quantityNeeded);

        if (! $source) {
            throw new InsufficientStockException($product, $salePoint);
        }

        $transfer = StockTransfer::create([
            'tenant_id' => $salePoint->tenant_id,
            'from_storage_id' => $source->warehouse->id,
            'to_storage_id' => $salePoint->id,
            'created_by' => $actor->id,
            'notes' => 'Auto-replenishment for POS sale',
            'transferred_at' => now(),
        ]);

        StockTransferLine::create([
            'tenant_id' => $salePoint->tenant_id,
            'stock_transfer_id' => $transfer->id,
            'product_id' => $productId,
            'quantity' => $quantityNeeded,
        ]);

        $this->executeStockTransfer->handle($transfer, $actor);
    }
}
