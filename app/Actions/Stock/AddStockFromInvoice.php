<?php

namespace App\Actions\Stock;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Storage;
use App\Models\Transaction;

class AddStockFromInvoice
{
    public function execute(Invoice $invoice, Storage $storage): void
    {
        $invoice->transactions->each(
            function (Transaction $transaction) use ($storage) {
                $transaction->for($storage)->add($storage);

                // If we have an actor in context, we should use it.
                // But this action seems to be used in bulk or automated context.
                // For now, let's assume we need to mark it delivered without specific actor or use current user.
                $transaction->deliver(auth()->user() ?? User::first(), $storage);
            }
        );

        $invoice->markAs(InvoiceStatus::Delivered);
    }
}
