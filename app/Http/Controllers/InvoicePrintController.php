<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

class InvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice)
    {
        $html = view('print.invoice', [
            'invoice' => $invoice
        ])->render();
        $path = "/invoices/{$invoice->serial_number}.pdf";
        Browsershot::html($html)
        ->save($path);
        return response()->file("/invoices/$invoice->serial_number");
    }
}
