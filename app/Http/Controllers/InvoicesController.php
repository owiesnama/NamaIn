<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Storage;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Spatie\Browsershot\Browsershot;

class InvoicesController extends Controller
{
    public function print(Invoice $invoice)
    {

        $deliveredRecords = $invoice->transactions->filter(fn ($t) => $t->delivered);
        $remainingRecords = $invoice->transactions->filter(fn ($t) => ! $t->delivered);

        $renderer = new ImageRenderer(
            new RendererStyle(80),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCode = $writer->writeString(route('invoices.show', $invoice));

        $pdf = Browsershot::html(
            view('print.invoice', [
                'invoice' => $invoice,
                'qr' => $qrCode,
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
        $invoice->load(['transactions', 'invocable', 'payments']);

        return inertia('Invoice', [
            'storages' => Storage::all(),
            'invoice' => $invoice,
        ]);
    }
}
