<?php

namespace App\Exports\Reports;

use App\Exports\Concerns\WithExportStyles;
use App\Queries\Reports\SalesReportQuery;
use App\Services\Utils\DatePreset;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class SalesReportExport implements FromArray, WithHeadings, WithStyles
{
    use WithExportStyles;

    public function __construct(protected array $filters = []) {}

    public function array(): array
    {
        $request = new Request($this->filters);
        $dates = (new DatePreset)->fromRequest($request);
        $groupBy = $this->filters['group_by'] ?? 'day';

        return (new SalesReportQuery)->get($dates['from'], $dates['to'], $groupBy);
    }

    public function headings(): array
    {
        return [__('Period'), __('Invoices'), __('Items Sold'), __('Revenue'), __('Avg Order Value')];
    }
}
