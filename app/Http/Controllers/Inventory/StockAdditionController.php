<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Stock\AddStockFromInvoice;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockRequest;
use App\Models\Invoice;
use App\Models\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdditionController extends Controller
{
    public function store(Storage $storage, StockRequest $request, AddStockFromInvoice $addStock): RedirectResponse
    {
        $this->authorize('manageStock', $storage);

        $invoice = Invoice::with('transactions.product')->findOrFail($request->validated('invoice'));

        if ($invoice->status === InvoiceStatus::Delivered) {
            throw ValidationException::withMessages([
                'invoice' => __('This invoice has already been fully delivered.'),
            ]);
        }

        DB::transaction(function () use ($invoice, $storage, $addStock) {
            $addStock->execute($invoice, $storage);
        });

        Cache::forget('low_stock_products');

        return back()->with('success', "Invoice items have been added to storage: {$storage->name}");
    }
}
