<?php

namespace App\Exports\Reports;

use App\Exports\Concerns\WithExportStyles;
use App\Queries\Reports\PosSessionReportQuery;
use App\Services\ReportDateResolver;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class PosSessionExport implements FromArray, WithHeadings, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $request = new Request($this->filters);
        $dates = (new ReportDateResolver)->resolve($request);

        return (new PosSessionReportQuery)->getData($dates['from'], $dates['to']);
    }

    public function headings(): array
    {
        return [
            __('ID'), __('Operator'), __('Storage'), __('Opened At'), __('Closed At'),
            __('Opening Float'), __('Cash Sales'), __('Expected Close'), __('Closing Float'),
            __('Variance'), __('Invoices'),
        ];
    }
}
