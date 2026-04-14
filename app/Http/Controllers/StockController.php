<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\StockRequest;
use App\Models\Invoice;
use App\Models\Storage;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    private const LOW_STOCK_CACHE_KEY = 'low_stock_products';

    public function add(Storage $storage, StockRequest $request)
    {
        $invoice = Invoice::findOrFail($request->validated('invoice'));

        $invoice->transactions->each(
            function (Transaction $transaction) use ($storage) {
                $transaction->for($storage)->add()->deliver();
            }
        );

        $invoice->markAs(InvoiceStatus::Delivered);

        Cache::forget(self::LOW_STOCK_CACHE_KEY);

        return back()->with('success', "Invoice items has being added to storage: {$storage->name} ");
    }

    public function deduct(Storage $storage, StockRequest $request)
    {
        $invoice = Invoice::findOrFail($request->validated('invoice'));
        $this->stockAvailableFor($invoice, $storage);
        $invoiceStatus = InvoiceStatus::Delivered;

        $invoice->transactions->each(function (Transaction $transaction) use ($storage, &$invoiceStatus) {
            if ($storage->hasEnoughStockFor($transaction->product_id, $transaction->base_quantity)) {
                $transaction->for($storage)
                    ->deduct()
                    ->deliver();

                return;
            }

            $remaining = $transaction->base_quantity - $storage->quantityOf($transaction->product_id);
            $transaction->base_quantity -= $remaining;
            $transaction->save();
            $transaction->for($storage)->deduct()->deliver();
            $transaction->replicateForRemaining($remaining);
            $invoiceStatus = InvoiceStatus::PartiallyDelivered;
        });

        $invoice->markAs($invoiceStatus);

        Cache::forget(self::LOW_STOCK_CACHE_KEY);

        return back()->with('success', "Invoice items has being deducted from storage: {$storage->name} ");
    }

    private function stockAvailableFor($invoice, $storage)
    {
        if ($invoice
            ->transactions
            ->filter(
                fn ($record) => $storage->hasStockFor($record->product_id, $record->base_quantity)
            )->count() === 0
        ) {
            throw ValidationException::withMessages(
                ['storage' => __('No stock available for any of the items on the invoice.')]
            );
        }
    }
}
