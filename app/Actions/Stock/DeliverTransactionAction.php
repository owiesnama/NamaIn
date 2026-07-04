<?php

namespace App\Actions\Stock;

use App\Models\Storage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeliverTransactionAction
{
    public function handle(Transaction $transaction, User $actor, ?Storage $fromStorage = null, bool $allowNegative = false): void
    {
        DB::transaction(function () use ($transaction, $actor, $fromStorage, $allowNegative) {
            $storage = $fromStorage ?? $transaction->storage;

            // Services carry no stock — they sell as line items with no ledger
            // movement. Only physical goods deduct on delivery.
            if (! $transaction->product?->isService()) {
                $storage->deductStock(
                    product: $transaction->product_id,
                    quantity: (int) $transaction->base_quantity,
                    reason: 'sale_delivery',
                    movable: $transaction,
                    actor: $actor,
                    allowNegative: $allowNegative
                );
            }

            // Mark as delivered
            $transaction->deliver($actor, $storage);
        });
    }
}
