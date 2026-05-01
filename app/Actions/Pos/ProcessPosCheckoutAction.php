<?php

namespace App\Actions\Pos;

use App\Actions\RecordPaymentAction;
use App\Actions\Stock\DeliverTransactionAction;
use App\Actions\Stock\ExecuteStockTransferAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TreasuryAccountType;
use App\Exceptions\InsufficientStockException;
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
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcessPosCheckoutAction
{
    public function __construct(
        private RecordPaymentAction $recordPayment,
        private DeliverTransactionAction $deliverAction,
        private FindReplenishmentSourceAction $findReplenishmentSource,
        private ExecuteStockTransferAction $executeStockTransfer,
    ) {}

    public function execute(
        PosSession $session,
        Collection $data,
        User $actor,
        ?string $idempotencyKey = null,
        bool $acknowledgeTransfers = false
    ): Invoice {
        if (! $session->isOpen()) {
            throw new DomainException('POS session is closed.');
        }

        $paymentMethod = PaymentMethod::tryFrom($data->get('payment_method', 'cash')) ?? PaymentMethod::Cash;

        if ($paymentMethod === PaymentMethod::Credit && ! $data->get('customer_id')) {
            throw new DomainException('Credit sales require a named customer.');
        }

        return DB::transaction(function () use ($session, $data, $actor, $idempotencyKey, $acknowledgeTransfers, $paymentMethod) {
            if ($idempotencyKey) {
                $existing = Invoice::where('tenant_id', $session->tenant_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            // 1. Replenish if needed
            foreach ($data->get('items') as $item) {
                $unit = isset($item['unit_id']) ? Unit::find($item['unit_id']) : null;
                $quantity = $item['quantity'] * ($unit->conversion_factor ?? 1);

                $available = $session->storage->quantityOf($item['product_id']);
                $needed = $quantity;

                if ($available < $needed) {
                    if (! $acknowledgeTransfers) {
                        throw new InsufficientStockException(Product::findOrFail($item['product_id']), $session->storage);
                    }

                    $this->replenish($session->storage, $item['product_id'], $needed - $available, $actor);
                }
            }

            // 2. Resolve Customer (Default to Walk-in if not provided)
            $customerId = $data->get('customer_id');
            $customerType = $data->get('customer_type', Customer::class);

            if (! $customerId && $customerType === Customer::class) {
                $customerId = Customer::firstOrCreate(
                    ['name' => 'Walk-in Customer', 'tenant_id' => $session->tenant_id],
                    ['address' => 'N/A', 'phone_number' => 'N/A', 'is_system' => true]
                )->id;
            }

            // 3. Create Invoice
            $invoice = Invoice::create([
                'tenant_id' => $session->tenant_id,
                'pos_session_id' => $session->id,
                'invocable_type' => $customerType,
                'invocable_id' => $customerId,
                'total' => $data->get('total'),
                'payment_method' => $data->get('payment_method', 'cash'),
                'payment_status' => PaymentStatus::Unpaid,
                'paid_amount' => 0,
                'status' => InvoiceStatus::Initial,
                'idempotency_key' => $idempotencyKey,
            ]);

            // 4. Create Transactions & Deduct Stock
            foreach ($data->get('items') as $item) {
                $unit = isset($item['unit_id']) ? Unit::find($item['unit_id']) : null;
                $quantity = $item['quantity'] * ($unit->conversion_factor ?? 1);

                $transaction = $invoice->transactions()->create([
                    'tenant_id' => $session->tenant_id,
                    'product_id' => $item['product_id'],
                    'storage_id' => $session->storage_id,
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'base_quantity' => $quantity,
                    'price' => $item['price'],
                    'delivered' => false,
                ]);

                $this->deliverAction->execute($transaction, $actor, $session->storage);
            }

            $invoice->markAs(InvoiceStatus::Delivered);

            // 5. Record payment only for methods that collect money at checkout
            if ($this->recordsImmediatePayment($paymentMethod)) {
                $cashDrawer = $paymentMethod === PaymentMethod::Cash
                    ? TreasuryAccount::where('sale_point_id', $session->storage_id)
                        ->ofType(TreasuryAccountType::Cash)
                        ->active()
                        ->first()
                    : null;

                $this->recordPayment->handle(
                    invoice: $invoice,
                    payable: $invoice->invocable,
                    amount: $invoice->total,
                    method: $paymentMethod,
                    direction: PaymentDirection::In,
                    options: [
                        'notes' => 'POS sale',
                        'treasury_account_id' => $cashDrawer?->id,
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

    private function replenish(Storage $salePoint, int $productId, int $quantityNeeded, User $actor): void
    {
        $source = $this->findReplenishmentSource
            ->handle(Product::findOrFail($productId), $quantityNeeded);

        if (! $source) {
            throw new InsufficientStockException($productId, $salePoint);
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

        $this->executeStockTransfer->execute($transfer, $actor);
    }
}
