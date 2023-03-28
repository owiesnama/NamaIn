<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Storage;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function add(Storage $storage)
    {
        $invoice = Invoice::find(request('invoice'));
        $invoice->details->each(function ($record) use ($storage) {
            $storage->addStock([
                'product' => $record->product_id,
                'quantity' => $record->base_quantity,
            ]);
        });
        $invoice->markAs(InvoiceStatus::Delivered);

        return back()->with('success', "Invoice items has being added to storage: {$storage->name} ");
    }

    public function deduct(Storage $storage)
    {
        $invoice = Invoice::find(request('invoice'));
        $invoiceStatus = InvoiceStatus::Initial;
        if ($invoice->details->filter(fn ($record) => $storage->hasStockFor($record->product_id, $record->base_quantity))->count() == 0) {
            throw ValidationException::withMessages(['storage' => 'Error Processing Request']);
        }
        $invoice->details->each(function ($record) use ($storage, &$invoiceStatus) {
            if ($storage->hasEnoughStockFor($record->product_id, $record->base_quantity)) {
                $storage->deductStock([
                    'product' => $record->product_id,
                    'quantity' => $record->base_quantity,
                ]);
                $invoiceStatus = InvoiceStatus::Delivered;

                return;
            }

            $remaining = $record->base_quantity - $storage->qunatityOf($record->product_id);
            $record->base_quantity -= $remaining;
            $record->delivered = true;
            $record->save();
            $storage->deductStock([
                'product' => $record->product_id,
                'quantity' => $remaining,
            ]);
            $newRecord = $record->replicate();
            $newRecord->delivered = false;
            $record->quantity = $record / $record->unit->conversion_factor;
            $newRecord->base_quantity = $remaining;
            $newRecord->save();
            $invoiceStatus = InvoiceStatus::PartiallyDelivered;
        });
        $invoice->markAs($invoiceStatus);

        return back()->with('flash', ['success' => "Invoice items has being deducted from storage: {$storage->name} "]);
    }
}
