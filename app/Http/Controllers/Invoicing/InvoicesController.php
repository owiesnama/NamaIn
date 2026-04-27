<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Storage;

class InvoicesController extends Controller
{
    public function show(Invoice $invoice)
    {
        $invoice->load(['transactions.product', 'transactions.unit', 'invocable', 'payments']);

        return inertia('Invoice', [
            'storages' => Storage::all(),
            'invoice' => $invoice,
        ]);
    }
}
