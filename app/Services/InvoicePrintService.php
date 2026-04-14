<?php

namespace App\Services;

use App\Models\Invoice;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Spatie\Browsershot\Browsershot;

class InvoicePrintService
{
    public function generatePdf(Invoice $invoice): string
    {
        $invoice->load(['invocable', 'transactions', 'payments']);

        $deliveredRecords = $invoice->transactions->filter(fn ($t) => $t->delivered);
        $remainingRecords = $invoice->transactions->filter(fn ($t) => ! $t->delivered);

        $renderer = new ImageRenderer(
            new RendererStyle(80),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $qrCode = $writer->writeString(route('invoices.show', $invoice));

        return Browsershot::html(
            view('print.invoice', [
                'invoice' => $invoice,
                'qr' => $qrCode,
            ])->with(compact('deliveredRecords', 'remainingRecords'))->render()
        )->noSandbox()->disableJavascript()->format('A4')->pdf();
    }
}
