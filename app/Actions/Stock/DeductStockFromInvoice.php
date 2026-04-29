<?php

namespace App\Actions\Stock;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Storage;
use App\Models\Transaction;
use App\Models\User;

class DeductStockFromInvoice
{
    public function execute(Invoice $invoice, Storage $storage, ?User $actor = null): void
    {
        $actor ??= auth()->user();

        abort_unless($actor instanceof User, 403, 'An authenticated user is required.');

        $invoiceStatus = InvoiceStatus::Delivered;

        $invoice->transactions->each(function (Transaction $transaction) use ($storage, &$invoiceStatus, $actor) {
            $baseQuantity = (float) $transaction->base_quantity;
            $availableQuantity = (float) $storage->quantityOf($transaction->product_id);

            if ($availableQuantity < $baseQuantity) {
                $remaining = $baseQuantity - $availableQuantity;
                $transaction->split($remaining);
                $invoiceStatus = InvoiceStatus::PartiallyDelivered;
            }

            $transaction->for($storage)
                ->deduct($storage);

            $transaction->deliver($actor, $storage);
        });

        $invoice->markAs($invoiceStatus);
    }
}
