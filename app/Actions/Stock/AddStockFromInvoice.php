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
                $transaction->for($storage)->add($storage)->deliver();
            }
        );

        $invoice->markAs(InvoiceStatus::Delivered);
    }
}
