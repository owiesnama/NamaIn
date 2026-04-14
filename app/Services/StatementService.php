<?php

namespace App\Services;

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
    public function generatePdf(Model $model, array $data, string $startDate, string $endDate): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(80),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);

        $routeName = str_contains(get_class($model), 'Customer') ? 'customers.statement' : 'suppliers.statement';
        $qrCode = $writer->writeString(route($routeName, [$model, 'start_date' => $startDate, 'end_date' => $endDate]));

        return Browsershot::html(
            view('print.statement', array_merge([
                'party' => $model,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'qr' => $qrCode,
            ], $data))->render()
        )->noSandbox()->disableJavascript()->format('A4')->pdf();
    }
}
