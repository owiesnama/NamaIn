<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Spatie\Browsershot\Browsershot;

class InvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice)
    {

        $deliveredRecords = $invoice->transactions->filter(fn ($t) => $t->delivered);
        $remainingRecords = $invoice->transactions->filter(fn ($t) => ! $t->delivered);

        $pdf = Browsershot::html(
            view('print.invoice', [
                'invoice' => $invoice,
            ])->with(compact('deliveredRecords', 'remainingRecords'))->render()
        )->format('A4')->pdf();

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename='invoice-{$invoice->serial_number}.pdf'",
        ];

        return response(content: $pdf, headers: $headers);
    }
}
