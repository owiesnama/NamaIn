<?php

namespace App\Http\Controllers\Invoicing;

use App\Actions\Stock\DeliverTransactionAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Storage;
use App\Models\Transaction;
use App\Services\InvoicePrintService;
use Illuminate\Http\Request;

class InvoicesController extends Controller
{
    public function print(Invoice $invoice, InvoicePrintService $service)
    {
        $pdf = $service->generatePdf($invoice);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename='invoice-{$invoice->serial_number}.pdf'",
        ];

        return response(content: $pdf, headers: $headers);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['transactions.product', 'transactions.unit', 'invocable', 'payments']);

        return inertia('Invoice', [
            'storages' => Storage::all(),
            'invoice' => $invoice,
        ]);
    }

    public function deliverTransaction(Transaction $transaction, Request $request, DeliverTransactionAction $action)
    {
        $request->validate([
            'storage_id' => 'required|exists:storages,id',
        ]);

        $storage = Storage::findOrFail($request->storage_id);

        try {
            $action->execute($transaction, auth()->user(), $storage);
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Item marked as delivered'));
    }
}
