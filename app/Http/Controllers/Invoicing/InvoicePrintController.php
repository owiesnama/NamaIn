<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoicePrintService;

class InvoicePrintController extends Controller
{
    public function show(Invoice $invoice, InvoicePrintService $service)
    {
        $pdf = $service->generatePdf($invoice);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename='invoice-{$invoice->serial_number}.pdf'",
        ];

        return response(content: $pdf, headers: $headers);
    }
}
