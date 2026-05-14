<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Stock\DeductStockFromInvoice;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockRequest;
use App\Models\Invoice;
use App\Models\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StockDeductionController extends Controller
{
    public function store(Storage $storage, StockRequest $request, DeductStockFromInvoice $deductStock): RedirectResponse
    {
        $this->authorize('manageStock', $storage);

        $invoice = Invoice::with('transactions.product')->findOrFail($request->validated('invoice'));

        try {
            DB::transaction(function () use ($invoice, $storage, $deductStock) {
                $deductStock->execute($invoice, $storage);
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        Cache::forget('low_stock_products');

        return back()->with('success', "Invoice items have been deducted from storage: {$storage->name}");
    }
}
