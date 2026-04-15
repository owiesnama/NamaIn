<?php

namespace App\Actions\Stock;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Storage;
use App\Models\Transaction;

class DeductStockFromInvoice
{
    public function execute(Invoice $invoice, Storage $storage): void
    {
        $invoiceStatus = InvoiceStatus::Delivered;

        $invoice->transactions->each(function (Transaction $transaction) use ($storage, &$invoiceStatus) {
            $baseQuantity = (float) $transaction->base_quantity;
            $availableQuantity = (float) $storage->quantityOf($transaction->product_id);

            if ($availableQuantity < $baseQuantity) {
                $remaining = $baseQuantity - $availableQuantity;
                $transaction->split($remaining);
                $invoiceStatus = InvoiceStatus::PartiallyDelivered;
            }

            $transaction->for($storage)
                ->deduct($storage)
                ->deliver();
        });

        $invoice->markAs($invoiceStatus);
    }
}
