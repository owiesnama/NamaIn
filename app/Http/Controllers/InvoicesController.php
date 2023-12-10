<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Browsershot\Browsershot;

class InvoicesController extends Controller
{
    public function print(Invoice $invoice)
    {

        $deliveredRecords = $invoice->transactions->filter(fn($t) => $t->delivered);
        $remainingRecords = $invoice->transactions->filter(fn($t) => !$t->delivered);

        $pdf = Browsershot::html(
            view('print.invoice', [
                'invoice' => $invoice,
                'qr' => QrCode::size(80)->generate(route('invoices.show', $invoice))
            ])->with(compact('deliveredRecords', 'remainingRecords'))->render()
        )->format('A4')->pdf();

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename='invoice-{$invoice->serial_number}.pdf'",
        ];

        return response(content: $pdf, headers: $headers);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['transactions', 'invocable']);
        return inertia("Invoice", [
            'storages' => Storage::all(),
            'invoice' => $invoice->load(['transactions', 'invocable'])
        ]);
    }
}
