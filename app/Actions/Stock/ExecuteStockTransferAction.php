<?php

namespace App\Actions\Stock;

use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExecuteStockTransferAction
{
    public function execute(StockTransfer $transfer, User $actor): void
    {
        if ($transfer->from_storage_id === $transfer->to_storage_id) {
            throw new InvalidArgumentException('Source and destination storage cannot be the same.');
        }

        DB::transaction(function () use ($transfer, $actor) {
            $lines = StockTransferLine::where('stock_transfer_id', $transfer->id)->get();

            foreach ($lines as $line) {
                // Deduct from source
                $transfer->fromStorage->deductStock(
                    product: $line->product_id,
                    quantity: $line->quantity,
                    reason: 'transfer_out',
                    movable: $line,
                    actor: $actor
                );

                // Add to destination
                $transfer->toStorage->addStock(
                    product: $line->product_id,
                    quantity: $line->quantity,
                    reason: 'transfer_in',
                    movable: $line,
                    actor: $actor
                );
            }

            $transfer->update([
                'transferred_at' => now(),
            ]);
        });
    }
}
