<?php

namespace App\Actions\Purchase;

use App\Jobs\BackfillProvisionalCostsJob;
use App\Models\Storage;
use App\Models\Transaction;
use App\Models\TransactionReceipt;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReceiveGoodsAction
{
    public function handle(Transaction $transaction, Storage $storage, int $quantity, User $actor, ?string $notes = null): TransactionReceipt
    {
        return DB::transaction(function () use ($transaction, $storage, $quantity, $actor, $notes) {
            // Lock transaction to prevent over-receiving
            $transaction = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

            if ($quantity > $transaction->remaining_quantity) {
                throw new DomainException("Cannot receive more than remaining quantity ({$transaction->remaining_quantity}).");
            }

            $receipt = TransactionReceipt::create([
                'tenant_id' => $transaction->tenant_id,
                'transaction_id' => $transaction->id,
                'storage_id' => $storage->id,
                'received_by' => $actor->id,
                'quantity' => $quantity,
                'received_at' => now(),
                'notes' => $notes,
            ]);

            // Add stock with reason purchase_receipt
            $storage->addStock(
                product: $transaction->product_id,
                quantity: $quantity,
                reason: 'purchase_receipt',
                movable: $receipt,
                actor: $actor
            );

            $transaction->product->recalculateAverageCost();
            BackfillProvisionalCostsJob::dispatch($transaction->tenant_id, $transaction->product_id);

            if ($transaction->isFullyReceived()) {
                $transaction->deliver($actor, $storage);
            }

            return $receipt;
        });
    }
}
