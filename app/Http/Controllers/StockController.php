<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Storage;
use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function add(Storage $storage)
    {
        $invoice = Invoice::find(request('invoice'));
        $invoice->transactions->each(
            fn (Transaction $transaction) =>
            $transaction->for($storage)->add()->deliver()
        );
        $invoice->markAs(InvoiceStatus::Delivered);

        return back()->with('success', "Invoice items has being added to storage: {$storage->name} ");
    }

    public function deduct(Storage $storage)
    {
        $invoice = Invoice::find(request('invoice'));
        $invoiceStatus = InvoiceStatus::Initial;
        $this->checkForStockAvailablity($invoice, $storage);
        $invoiceStatus = InvoiceStatus::Delivered;
        $invoice->transactions->each(function (Transaction $transaction) use ($storage, &$invoiceStatus) {
            if ($storage->hasEnoughStockFor($transaction->product_id, $transaction->base_quantity)) {
                return $transaction->for($storage)->deduct()->deliver();
            }
            $remaining = $transaction->base_quantity - $storage->qunatityOf($transaction->product_id);
            $transaction->base_quantity -= $remaining;
            $transaction->for($storage)->deduct()->deliver();
            $newTransaction = $transaction->replicate();
            $newTransaction->delivered = false;
            $newTransaction->quantity = $transaction->unit ? ($remaining / $transaction->unit->conversion_factor) : $remaining;
            $transaction->price = $transaction->price / $remaining;
            $newTransaction->base_quantity = $transaction->unit ? ($remaining * $transaction->unit->conversion_factor) : $remaining;;
            $newTransaction->save();
            $invoiceStatus = InvoiceStatus::PartiallyDelivered;
        });
        $invoice->markAs($invoiceStatus);

        return back()->with('flash', ['success' => "Invoice items has being deducted from storage: {$storage->name} "]);
    }

    private function checkForStockAvailablity($invoice, $storage)
    {
        if ($invoice->transactions->filter(fn ($record) => $storage->hasStockFor($record->product_id, $record->base_quantity))->count() == 0) {
            throw ValidationException::withMessages(['storage' => 'Error Processing Request']);
        }
    }
}
