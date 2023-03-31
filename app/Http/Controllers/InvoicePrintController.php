<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice)
    {
        $pdf = Pdf::loadView('print.invoice', [
            'invoice' => $invoice,
        ]);

        return $pdf->stream();
    }
}
