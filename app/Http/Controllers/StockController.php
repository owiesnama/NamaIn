<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceDetails;
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
        $invoice->markAsUsed();

        return back()->with('success', "Invoice items has being added to storage: {$storage->name} ");
    }

    public function deduct(Storage $storage)
    {
        $invoice = Invoice::find(request('invoice'));
        if ($invoice->details->contains(fn ($record) => $storage->hasNoEnoughStockFor($record->product_id, $record->base_quantity))) {
            throw ValidationException::withMessages([
                'storage' => 'Storage dose not contain any product delevierable for this invoice'
            ]);
        }
        $invoice->details->each(function ($record) use ($storage) {
            $storage->deductStock([
                'product' => $record->product_id,
                'quantity' => $record->base_quantity,
            ]);
        });
        $invoice->markAsUsed();
        return back()->with('flash', ['success' => "Invoice items has being deducted from storage: {$storage->name} "]);
    }
}
