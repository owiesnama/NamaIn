<?php

namespace App\Exports\Reports;

use App\Exports\Concerns\WithExportStyles;
use App\Queries\Reports\ReconciliationReportQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

/**
 * `report-reconciliation` (Design 04 §6.3, R14): item-level detail —
 * open/resolved, type, device, and the occurred→resolved timeline — for the
 * pilot's human-loop audit. Queued through the existing GenerateExportJob,
 * which rebinds tenant + locale.
 */
class ReconciliationReportExport implements FromArray, WithHeadings, WithStyles
{
    use WithExportStyles;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $request = new Request($this->filters);
        $dates = (new DatePreset)->fromRequest($request);

        return (new ReconciliationReportQuery)->get($dates['from'], $dates['to']);
    }

    public function headings(): array
    {
        return [
            __('Item'),
            __('Type'),
            __('Status'),
            __('Resolution'),
            __('Device'),
            __('Register'),
            __('Occurred'),
            __('Detected'),
            __('Resolved'),
            __('Resolved by'),
        ];
    }
}
