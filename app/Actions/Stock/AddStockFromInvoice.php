<?php

namespace App\Actions\Stock;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Storage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddStockFromInvoice
{
    public function handle(Invoice $invoice, Storage $storage, ?User $actor = null): void
    {
        $actor ??= auth()->user();

        abort_unless($actor instanceof User, 403, 'An authenticated user is required.');

        DB::transaction(function () use ($invoice, $storage, $actor) {
            $invoice->transactions->each(
                function (Transaction $transaction) use ($storage, $actor) {
                    $transaction->for($storage)->add($storage);
                    $transaction->deliver($actor, $storage);
                }
            );

            $invoice->markAs(InvoiceStatus::Delivered);
        });
    }
}
