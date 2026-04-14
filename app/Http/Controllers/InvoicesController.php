<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Storage;
use App\Services\InvoicePrintService;

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
            'storages' => Storage::limit(20)->get(),
            'invoice' => $invoice,
        ]);
    }
}
