<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Storage;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function add(Storage $storage)
    {
        $invoice = Invoice::find(request('invoice'));
        $invoice->details->each(function ($record) use ($storage) {
            $storage->addStock([
                "product" => $record->product_id,
                "quantity" => $record->quantity
            ]);
        });
        $invoice->markAsUsed();
        return back()->with('flash', ['message' => "Invoice items has being added to storage: {$storage->name} "]);
    }

    public function deduct(Storage $storage)
    {
        dd(request());
    }
}
