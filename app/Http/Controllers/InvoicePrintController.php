<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Spatie\Browsershot\Browsershot;

class InvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice)
    {
        $html = view('print.invoice', [
            'invoice' => $invoice,
        ])->render();
        $path = "/invoices/{$invoice->serial_number}.pdf";
        Browsershot::html($html)
            ->save($path);

        return response()->file("/invoices/$invoice->serial_number");
    }
}
