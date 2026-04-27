<?php

namespace App\Actions\Stock;

use App\Models\Customer;
use App\Models\Transaction;

class ReverseTransactionAction
{
    public function execute(Transaction $transaction): void
    {
        $storage = $transaction->storage;

        if ($transaction->invoice?->invocable_type === Customer::class) {
            $storage->addStock(
                product: $transaction->product_id,
                quantity: (int) $transaction->base_quantity,
                reason: 'sales_return',
                movable: $transaction
            );
        } else {
            $storage->deductStock(
                product: $transaction->product_id,
                quantity: (int) $transaction->base_quantity,
                reason: 'purchase_return',
                movable: $transaction
            );
        }
    }
}
