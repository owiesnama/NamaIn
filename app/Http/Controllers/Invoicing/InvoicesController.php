<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Storage;

class InvoicesController extends Controller
{
    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $invoice->load([
            'transactions' => fn ($q) => $q->withSum('receipts', 'quantity'),
            'transactions.product',
            'transactions.unit',
            'invocable',
            'payments',
        ]);

        $invoice->transactions->each->append(['received_quantity', 'remaining_quantity']);

        return inertia('Invoice', [
            'storages' => Storage::all(),
            'invoice' => $invoice,
        ]);
    }
}
