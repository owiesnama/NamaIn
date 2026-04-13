<?php

namespace App\Services;

use App\Models\Payment;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;
use Spatie\Browsershot\Browsershot;

class StatementService
{
    /**
     * Generate a PDF statement for a given model (Customer/Supplier).
     */
    public function generatePdf(Model $model, string $startDate, string $endDate): string
    {
        $invoices = $model->invoices()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('payments')
            ->get();

        $payments = Payment::where(function ($query) use ($model) {
            $query->whereHas('invoice', fn ($q) => $q->where('invocable_id', $model->id)->where('invocable_type', get_class($model)))
                ->orWhere(fn ($q) => $q->where('payable_id', $model->id)->where('payable_type', get_class($model)));
        })->whereBetween('paid_at', [$startDate, $endDate])->latest()->get();

        $opening_balance = $model->calculateAccountBalance($startDate);

        $renderer = new ImageRenderer(
            new RendererStyle(80),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);

        $routeName = str_contains(get_class($model), 'Customer') ? 'customers.statement' : 'suppliers.statement';
        $qrCode = $writer->writeString(route($routeName, [$model, 'start_date' => $startDate, 'end_date' => $endDate]));

        return Browsershot::html(
            view('print.statement', [
                'customer' => $model, // The view uses 'customer' for both
                'invoices' => $invoices,
                'payments' => $payments,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'opening_balance' => $opening_balance,
                'qr' => $qrCode,
            ])->render()
        )->noSandbox()->disableJavascript()->format('A4')->pdf();
    }
}
