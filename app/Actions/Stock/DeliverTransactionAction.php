<?php

namespace App\Actions\Stock;

use App\Models\Storage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeliverTransactionAction
{
    public function execute(Transaction $transaction, User $actor, ?Storage $fromStorage = null): void
    {
        DB::transaction(function () use ($transaction, $actor, $fromStorage) {
            $storage = $fromStorage ?? $transaction->storage;

            // Deduct stock
            $storage->deductStock(
                product: $transaction->product_id,
                quantity: (int) $transaction->base_quantity,
                reason: 'sale_delivery',
                movable: $transaction,
                actor: $actor
            );

            // Mark as delivered
            $transaction->deliver($actor, $storage);
        });
    }
}
