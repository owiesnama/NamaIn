<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Stock\AddStockFromInvoice;
use App\Actions\Stock\DeductStockFromInvoice;
use App\Enums\InvoiceStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockRequest;
use App\Models\Invoice;
use App\Models\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    private const LOW_STOCK_CACHE_KEY = 'low_stock_products';

    public function __construct(
        private AddStockFromInvoice $addStock,
        private DeductStockFromInvoice $deductStock,
    ) {}

    public function add(Storage $storage, StockRequest $request): RedirectResponse
    {
        $this->authorize('manageStock', $storage);

        $invoice = Invoice::with('transactions.product')->findOrFail($request->validated('invoice'));

        $this->validateInvoiceNotDelivered($invoice);

        DB::transaction(function () use ($invoice, $storage) {
            $this->addStock->execute($invoice, $storage);
        });

        $this->clearStockCache();

        return back()->with('success', "Invoice items have been added to storage: {$storage->name}");
    }

    public function deduct(Storage $storage, StockRequest $request): RedirectResponse
    {
        $this->authorize('manageStock', $storage);

        $invoice = Invoice::with('transactions.product')->findOrFail($request->validated('invoice'));

        $this->validateInvoiceNotDelivered($invoice);

        try {
            DB::transaction(function () use ($invoice, $storage) {
                $this->deductStock->execute($invoice, $storage);
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->clearStockCache();

        return back()->with('success', "Invoice items have been deducted from storage: {$storage->name}");
    }

    private function validateInvoiceNotDelivered(Invoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Delivered) {
            throw ValidationException::withMessages([
                'invoice' => __('This invoice has already been fully delivered.'),
            ]);
        }
    }

    private function clearStockCache(): void
    {
        Cache::forget(self::LOW_STOCK_CACHE_KEY);
    }
}
