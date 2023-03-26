<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Storage;

class StockController extends Controller
{
    public function add(Storage $storage)
    {
        $invoice = Invoice::find(request('invoice'));
        $invoice->details->each(function ($record) use ($storage) {
            $storage->addStock([
                'product' => $record->product_id,
                'quantity' => $record->getBaseQuantity(),
            ]);
        });
        $invoice->markAsUsed();

        return back()->with('flash', ['message' => "Invoice items has being added to storage: {$storage->name} "]);
    }

    public function deduct(Storage $storage)
    {
        $invoice = Invoice::find(request('invoice'));
        $unDelevirableProductsDetails = collect();
        $invoice->details->each(function ($record) use ($storage, $unDelevirableProductsDetails) {
            if ($storage->hasStockFor($record->product_id, $record->getBaseQuantity())) {
                $storage->deductStock([
                    'product' => $record->product_id,
                    'quantity' => $record->getBaseQuantity(),
                ]);
            } else {
                $unDelevirableProductsDetails->push($record);
            }
        });
        $invoice->markAsUsed();

        return back()->with('flash', ['message' => "Invoice items has being deducted from storage: {$storage->name} "]);
    }
}
